<?php

enum StatusCode: string
{
    case QUEUED = 'queued'; // new mail
    case PROCESSING = 'processing'; // mail is being processed
    case SENT = 'sent'; // mail sent successfully
    case DELIVERED = 'delivered'; // mail delivered successfully
    case FAILED = 'failed'; // mail sending failed
}