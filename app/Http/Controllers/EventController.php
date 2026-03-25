<?php
// app/Http/Controllers/EventController.php

namespace App\Http\Controllers;

use App\Http\Resources\EventResource;
use App\Mail\EventConfirmationMail;
use App\Mail\EventReminderMail;
use App\Models\CampusEvent;
use App\Models\EventAttendance;
use App\Traits\PublishesRedisEvents;
use App\Enums\RealtimeEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class EventController extends Controller
{
    use PublishesRedisEvents;

    // ── Public ────────────────────────────────────────────────────────

    /**
     * GET /events — Liste des événements publiés (filtres: upcoming, past, type)
     */
    public function index(Request $request)
    {
        $query = CampusEvent::with(['organizer'])
            ->withCount(['registeredAttendances as attendees_count'])
            ->published()
            ->latest('start_date');

        if ($request->filled('type')) {
            $query->where('event_type', $request->type);
        }

        if ($request->boolean('upcoming', true)) {
            $query->where('start_date', '>=', now());
        }

        return EventResource::collection($query->paginate(12));
    }

    /**
     * GET /events/{id} — Détail d'un événement
     */
    public function show(Request $request, $id)
    {
        $event = CampusEvent::with(['organizer'])
            ->withCount(['registeredAttendances as attendees_count'])
            ->findOrFail($id);

        if (!$event->isPublished() && optional($request->user())->role !== 'admin') {
            return response()->json(['message' => 'Événement non disponible'], 404);
        }

        $data = (new EventResource($event))->toArray($request);

        // Add user-specific fields if authenticated
        if ($request->user()) {
            $data['is_registered'] = $event->isUserRegistered($request->user()->id);
        }

        return response()->json($data);
    }

    // ── Auth (admin/bde/pedagogical only for CRUD) ────────────────────

    /**
     * POST /events — Créer un événement (brouillon)
     */
    public function store(Request $request)
    {
        $this->requireOrganizer($request);

        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'description'  => 'required|string',
            'location'     => 'required|string|max:255',
            'start_date'   => 'required|date|after:now',
            'end_date'     => 'required|date|after:start_date',
            'capacity'     => 'nullable|integer|min:1',
            'event_type'   => 'nullable|in:general,workshop,conference,networking,sports',
            'target_roles' => 'nullable|array',
            'target_roles.*' => 'in:student,alumni,bde_member,pedagogical,company,admin',
        ]);

        $event = CampusEvent::create([
            ...$validated,
            'organizer_id' => $request->user()->id,
        ]);

        return response()->json(new EventResource($event->load('organizer')), 201);
    }

    /**
     * PATCH /events/{id} — Modifier un événement
     */
    public function update(Request $request, $id)
    {
        $event = CampusEvent::findOrFail($id);
        $this->requireOrganizer($request, $event);

        $validated = $request->validate([
            'title'        => 'sometimes|string|max:255',
            'description'  => 'sometimes|string',
            'location'     => 'sometimes|string|max:255',
            'start_date'   => 'sometimes|date',
            'end_date'     => 'sometimes|date|after:start_date',
            'capacity'     => 'nullable|integer|min:1',
            'event_type'   => 'nullable|in:general,workshop,conference,networking,sports',
            'target_roles' => 'nullable|array',
            'target_roles.*' => 'in:student,alumni,bde_member,pedagogical,company,admin',
        ]);

        $attendeeIds = EventAttendance::where('event_id', $event->id)
            ->where('status', 'registered')
            ->pluck('user_id')
            ->toArray();

        $event->update($validated);
        $event->load('organizer');

        if (!empty($attendeeIds)) {
            $this->publishEvent(RealtimeEvent::EVENT_UPDATED, [
                'eventId'     => $event->id,
                'eventTitle'  => $event->title,
                'location'    => $event->location,
                'startDate'   => $event->start_date->toISOString(),
                'attendeeIds' => $attendeeIds,
            ]);
        }

        return response()->json(new EventResource($event));
    }

    /**
     * DELETE /events/{id} — Annuler/supprimer un événement (organisateur ou admin)
     * Notifie tous les inscrits via Redis.
     */
    public function destroy(Request $request, $id)
    {
        $event = CampusEvent::findOrFail($id);
        $this->requireOrganizer($request, $event);

        $attendeeIds = EventAttendance::where('event_id', $event->id)
            ->where('status', 'registered')
            ->pluck('user_id')
            ->toArray();

        $eventTitle = $event->title;

        if ($event->cover_image) {
            Storage::disk('public')->delete($event->cover_image);
        }

        $event->delete();

        if (!empty($attendeeIds)) {
            $this->publishEvent(RealtimeEvent::EVENT_CANCELLED, [
                'eventId'     => $id,
                'eventTitle'  => $eventTitle,
                'attendeeIds' => $attendeeIds,
            ]);
        }

        return response()->json(['message' => 'Événement annulé']);
    }

    /**
     * POST /events/{id}/cover — Upload image de couverture
     */
    public function uploadCover(Request $request, $id)
    {
        $event = CampusEvent::findOrFail($id);
        $this->requireOrganizer($request, $event);

        $request->validate(['cover' => 'required|image|max:5120']);

        if ($event->cover_image) {
            Storage::disk('public')->delete($event->cover_image);
        }

        $path = $request->file('cover')->store('events/covers', 'public');
        $event->update(['cover_image' => $path]);

        return response()->json(['cover_image' => $path]);
    }

    /**
     * POST /events/{id}/publish — Publier un événement
     */
    public function publish(Request $request, $id)
    {
        $event = CampusEvent::findOrFail($id);
        $this->requireOrganizer($request, $event);

        $event->update(['published_at' => now()]);
        $event->load('organizer');
        $event->loadCount(['registeredAttendances as attendees_count']);

        $this->publishEvent(RealtimeEvent::EVENT_PUBLISHED, [
            'eventId'       => $event->id,
            'title'         => "Nouvel événement : {$event->title}",
            'body'          => $event->description,
            'location'      => $event->location,
            'startDate'     => $event->start_date->toISOString(),
            'organizerName' => $event->organizer->name,
            'eventType'     => $event->event_type,
        ]);

        return response()->json(new EventResource($event));
    }

    // ── Registration ─────────────────────────────────────────────────

    /**
     * POST /events/{id}/attend — S'inscrire à un événement
     */
    public function attend(Request $request, $id)
    {
        $event = CampusEvent::with('organizer')->findOrFail($id);
        $user  = $request->user();

        if (!$event->isPublished()) {
            return response()->json(['message' => 'Événement non disponible'], 404);
        }

        if ($event->start_date < now()) {
            return response()->json(['message' => 'Cet événement est déjà passé'], 422);
        }

        // Check role restriction
        if ($event->target_roles && !in_array($user->role, $event->target_roles)) {
            return response()->json(['message' => 'Vous n\'êtes pas autorisé à vous inscrire à cet événement'], 403);
        }

        if ($event->isUserRegistered($user->id)) {
            return response()->json(['message' => 'Vous êtes déjà inscrit à cet événement'], 422);
        }

        if ($event->isFull()) {
            return response()->json(['message' => 'Cet événement est complet'], 422);
        }

        $attendance = EventAttendance::updateOrCreate(
            ['event_id' => $event->id, 'user_id' => $user->id],
            ['status' => 'registered', 'reminder_sent' => false],
        );

        // Send confirmation email
        try {
            Mail::to($user->email)->send(new EventConfirmationMail($event, $user));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Event confirmation email failed: {$e->getMessage()}");
        }

        // Notify via realtime
        $this->publishEvent(RealtimeEvent::EVENT_ATTENDANCE_CONFIRMED, [
            'eventId'      => $event->id,
            'eventTitle'   => $event->title,
            'userId'       => $user->id,
            'startDate'    => $event->start_date->toISOString(),
            'location'     => $event->location,
        ]);

        $event->loadCount(['registeredAttendances as attendees_count']);

        return response()->json([
            'message'      => 'Inscription confirmée ! Un email de confirmation vous a été envoyé.',
            'attendance_id' => $attendance->id,
            'attendees_count' => $event->attendees_count,
        ], 201);
    }

    /**
     * DELETE /events/{id}/attend — Se désinscrire d'un événement
     */
    public function unattend(Request $request, $id)
    {
        $event = CampusEvent::findOrFail($id);
        $user  = $request->user();

        $attendance = EventAttendance::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->where('status', 'registered')
            ->first();

        if (!$attendance) {
            return response()->json(['message' => 'Vous n\'êtes pas inscrit à cet événement'], 422);
        }

        if ($event->start_date < now()) {
            return response()->json(['message' => 'Impossible de se désinscrire d\'un événement passé'], 422);
        }

        $attendance->update(['status' => 'cancelled']);

        $event->loadCount(['registeredAttendances as attendees_count']);

        return response()->json([
            'message'         => 'Désinscription effectuée',
            'attendees_count' => $event->attendees_count,
        ]);
    }

    /**
     * GET /events/{id}/attendees — Liste des inscrits (organisateur/admin)
     */
    public function attendees(Request $request, $id)
    {
        $event = CampusEvent::findOrFail($id);
        $user  = $request->user();

        if ($user->role !== 'admin' && $event->organizer_id !== $user->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $attendances = EventAttendance::with('user')
            ->where('event_id', $event->id)
            ->where('status', 'registered')
            ->paginate(50);

        return response()->json($attendances);
    }

    // ── Admin: list all events (published + drafts) ───────────────────

    /**
     * GET /admin/events — Tous les événements pour l'admin
     */
    public function adminIndex(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $events = CampusEvent::with('organizer')
            ->withCount(['registeredAttendances as attendees_count'])
            ->latest()
            ->paginate(20);

        return EventResource::collection($events);
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function requireOrganizer(Request $request, ?CampusEvent $event = null): void
    {
        $user = $request->user();
        $allowed = in_array($user->role, ['admin', 'bde_member', 'pedagogical']);

        if (!$allowed) {
            abort(403, 'Non autorisé');
        }

        // If event provided, check ownership (unless admin)
        if ($event && $user->role !== 'admin' && $event->organizer_id !== $user->id) {
            abort(403, 'Non autorisé');
        }
    }
}
