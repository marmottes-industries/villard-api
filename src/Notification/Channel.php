<?php

namespace App\Notification;

/**
 * Delivery channels a notification can be sent through. Each channel maps to one
 * transport (see {@see Notifier}). Add a case here and a matching transport to
 * support a new delivery medium.
 */
enum Channel: string
{
    case Mail = 'mail';
    case Push = 'push';
}
