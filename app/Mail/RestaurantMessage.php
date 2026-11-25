<?php

namespace App\Mail;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope};
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\{DB, Log, Mail};
use Throwable;

class RestaurantMessage extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [30, 120, 300];

    public function __construct(public string $title, public string $bodyText, public ?string $link = null)
    {
        $this->onConnection('database')->beforeCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->title.' | '.Setting::current()['name']);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.message', with: ['business' => Setting::current()]);
    }

    public static function afterTransaction(string $email, string $title, string $message, ?string $link = null): void
    {
        $queue = static function () use ($email, $title, $message, $link) {
            try {
                self::sendTo($email, $title, $message, $link);
            } catch (Throwable $error) {
                Log::warning('Restaurant notification could not be queued.', ['type' => $error::class, 'title' => $title]);
            }
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($queue);
        } else {
            $queue();
        }
    }

    public static function sendTo(string $email, string $title, string $message, ?string $link = null): void
    {
        if ($email !== '' && config('restaurant.send_mail')) {
            Mail::to($email)->queue(new self($title, $message, $link));
        }
    }
}


