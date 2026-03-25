<?php
// app/Enums/RealtimeEvent.php
namespace App\Enums;

enum RealtimeEvent: string
{
    // Applications
    case APPLICATION_CREATED = 'application.created';
    case APPLICATION_STATUS_UPDATED = 'application.status_updated';

    // Messages
    case MESSAGE_CREATED = 'message.created';

    // Events/Feed
    case EVENT_PUBLISHED = 'event.published';
    case EVENT_ATTENDANCE_CONFIRMED = 'event.attendance.confirmed';
    case EVENT_UPDATED = 'event.updated';
    case EVENT_CANCELLED = 'event.cancelled';
    case EVENT_REMINDER = 'event.reminder';
    case POST_CREATED = 'post.created';
    case COMMENT_ADDED = 'comment.added';
}
