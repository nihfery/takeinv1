<?php

namespace Tests\Feature\Runtime;

use App\Modules\Chat\Domain\Events\ChatMessageSent;
use App\Modules\Chat\Domain\Events\ChatThreadUpdated;
use App\Modules\Chat\Infrastructure\Persistence\Models\ChatMessage;
use App\Modules\Chat\Infrastructure\Persistence\Models\ChatThread;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Notification\Domain\Events\UserNotificationSent;
use App\Modules\Notification\Infrastructure\Persistence\Models\AppNotification;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueuedBroadcastEventsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        if ($this->app?->bound('db')) {
            DB::purge('runtime_events');
        }

        parent::tearDown();
    }

    public function test_realtime_events_use_the_bounded_notifications_queue_contract(): void
    {
        Queue::fake();

        $events = [
            $this->chatMessageEvent(),
            $this->chatThreadEvent(),
            $this->notificationEvent(),
        ];

        foreach ($events as $event) {
            event($event);
        }

        Queue::assertPushedTimes(BroadcastEvent::class, 3);

        foreach ($events as $expectedEvent) {
            Queue::assertPushedOn(
                'notifications',
                BroadcastEvent::class,
                function (BroadcastEvent $job) use ($expectedEvent): bool {
                    if (! $job->event instanceof $expectedEvent) {
                        return false;
                    }

                    $this->assertInstanceOf(ShouldBroadcast::class, $job->event);
                    $this->assertNotInstanceOf(ShouldBroadcastNow::class, $job->event);
                    $this->assertInstanceOf(ShouldDispatchAfterCommit::class, $job->event);
                    $this->assertInstanceOf(ShouldRescue::class, $job->event);
                    $this->assertTrue($job->afterCommit);
                    $this->assertSame(3, $job->tries);
                    $this->assertSame(15, $job->timeout);
                    $this->assertSame(10, $job->backoff);

                    return true;
                }
            );
        }
    }

    public function test_channel_names_event_names_and_payloads_remain_unchanged(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10T12:40:00+07:00'));

        $messageEvent = $this->chatMessageEvent();
        $threadEvent = $this->chatThreadEvent();
        $notificationEvent = $this->notificationEvent();

        $this->assertSame('private-chat.thread.42', (string) $messageEvent->broadcastOn()[0]);
        $this->assertSame('message.sent', $messageEvent->broadcastAs());
        $this->assertSame([
            'message' => [
                'id' => 84,
                'thread_id' => 42,
                'sender_id' => 7,
                'sender_role' => 'provider',
                'sender_name' => 'Ayu Sari',
                'sender_email' => 'ayu@example.test',
                'sender_initials' => 'AS',
                'body' => 'Booking sudah dikonfirmasi.',
                'attachment' => null,
                'is_mine' => false,
                'sent_at' => '12:34',
                'sent_date' => '10 Aug 2026',
                'created_at' => '2026-08-10T12:34:00+07:00',
                'delivery_status' => 'sent',
                'delivery_label' => 'Dikirim',
                'read_at' => null,
            ],
        ], $messageEvent->broadcastWith());

        $this->assertSame('private-chat.thread.42', (string) $threadEvent->broadcastOn()[0]);
        $this->assertSame('thread.updated', $threadEvent->broadcastAs());
        $this->assertSame([
            'thread' => [
                'id' => 42,
                'conversation_type' => 'provider_admin',
                'ticket_status' => 'closed',
                'status' => 'closed',
                'ticket_rejection_reason' => 'Resolved',
                'closed_at' => '2026-08-10T12:35:00+07:00',
                'closed_by' => 'Admin TakeIn',
                'last_admin_read_at' => null,
                'last_provider_read_at' => null,
                'last_branch_read_at' => null,
                'read_receipts' => [
                    'last_admin_read_at' => null,
                    'last_provider_read_at' => null,
                    'last_branch_read_at' => null,
                ],
            ],
        ], $threadEvent->broadcastWith());

        $this->assertSame('private-notifications.user.7', (string) $notificationEvent->broadcastOn()[0]);
        $this->assertSame('notification.created', $notificationEvent->broadcastAs());
        $this->assertSame([
            'notification' => [
                'id' => 99,
                'type' => 'booking.confirmed',
                'title' => 'Booking confirmed',
                'body' => 'Your booking is ready.',
                'url' => '/bookings/BKG-001',
                'data' => ['booking_code' => 'BKG-001'],
                'is_read' => false,
                'time' => '6 minutes ago',
                'created_at' => '2026-08-10T12:34:00+07:00',
            ],
            'unread_count' => 4,
        ], $notificationEvent->broadcastWith());
    }

    public function test_sync_broadcast_job_is_released_only_after_transaction_commit(): void
    {
        config()->set('queue.default', 'sync');

        $processed = 0;

        Queue::before(function (JobProcessing $event) use (&$processed): void {
            if ($event->job->resolveName() === ChatThreadUpdated::class) {
                $processed++;
            }
        });

        $connection = $this->runtimeConnection();
        $connection->beginTransaction();

        event($this->chatThreadEvent());

        $this->assertSame(0, $processed);

        $connection->commit();

        $this->assertSame(1, $processed);
    }

    public function test_sync_broadcast_job_is_discarded_when_transaction_rolls_back(): void
    {
        config()->set('queue.default', 'sync');

        $processed = 0;

        Queue::before(function (JobProcessing $event) use (&$processed): void {
            if ($event->job->resolveName() === ChatThreadUpdated::class) {
                $processed++;
            }
        });

        $connection = $this->runtimeConnection();
        $connection->beginTransaction();

        event($this->chatThreadEvent());

        $this->assertSame(0, $processed);

        $connection->rollBack();

        $this->assertSame(0, $processed);
    }

    private function runtimeConnection(): ConnectionInterface
    {
        config()->set('database.connections.runtime_events', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        return DB::connection('runtime_events');
    }

    private function chatMessageEvent(): ChatMessageSent
    {
        $thread = $this->thread();
        $sender = new User();
        $sender->forceFill([
            'id' => 7,
            'name' => 'Ayu Sari',
            'email' => 'ayu@example.test',
        ]);

        $message = new ChatMessage();
        $message->forceFill([
            'id' => 84,
            'chat_thread_id' => 42,
            'sender_id' => 7,
            'sender_role' => 'provider',
            'body' => 'Booking sudah dikonfirmasi.',
            'created_at' => Carbon::parse('2026-08-10T12:34:00+07:00'),
        ]);
        $message->setRelation('sender', $sender);
        $message->setRelation('thread', $thread);

        return new ChatMessageSent($message);
    }

    private function chatThreadEvent(): ChatThreadUpdated
    {
        return new ChatThreadUpdated($this->thread());
    }

    private function notificationEvent(): UserNotificationSent
    {
        $notification = new AppNotification();
        $notification->forceFill([
            'id' => 99,
            'user_id' => 7,
            'type' => 'booking.confirmed',
            'title' => 'Booking confirmed',
            'body' => 'Your booking is ready.',
            'url' => '/bookings/BKG-001',
            'data' => ['booking_code' => 'BKG-001'],
            'read_at' => null,
            'created_at' => Carbon::parse('2026-08-10T12:34:00+07:00'),
        ]);

        return new UserNotificationSent($notification, 4);
    }

    private function thread(): ChatThread
    {
        $closer = new User();
        $closer->forceFill([
            'id' => 1,
            'name' => 'Admin TakeIn',
        ]);

        $thread = new ChatThread();
        $thread->forceFill([
            'id' => 42,
            'conversation_type' => 'provider_admin',
            'ticket_status' => 'closed',
            'status' => 'closed',
            'ticket_rejection_reason' => 'Resolved',
            'closed_at' => Carbon::parse('2026-08-10T12:35:00+07:00'),
            'last_admin_read_at' => null,
            'last_provider_read_at' => null,
            'last_branch_read_at' => null,
        ]);
        $thread->setRelation('provider', null);
        $thread->setRelation('providerUser', null);
        $thread->setRelation('branchUser', null);
        $thread->setRelation('closer', $closer);

        return $thread;
    }
}
