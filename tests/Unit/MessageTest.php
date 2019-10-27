<?php

namespace Musonza\Chat\Tests;

use Chat;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Musonza\Chat\Models\Conversation;
use Musonza\Chat\Models\Participation;
use Musonza\Chat\Models\Message;
use Musonza\Chat\Tests\Helpers\Models\Bot;
use Musonza\Chat\Tests\Helpers\Models\Client;
use Musonza\Chat\Tests\Helpers\Models\User;

class MessageTest extends TestCase
{
    use DatabaseMigrations;

    /** @test */
    public function it_can_send_a_message()
    {
        $conversation = Chat::createConversation([$this->users[0], $this->users[1]]);

        Chat::message('Hello')
            ->from($this->users[1])
            ->to($conversation)
            ->send();

        $this->assertEquals($conversation->messages->count(), 1);
    }

    /** @test */
    public function it_can_send_a_message_between_models()
    {
        /** @var Client $clientModel */
        $clientModel = factory(Client::class)->create();
        $userModel = factory(User::class)->create();
        $botModel = factory(Bot::class)->create();

        $conversation = Chat::createConversation([$clientModel, $userModel, $botModel]);

        Chat::message('Hello')
            ->from($userModel)
            ->to($conversation)
            ->send();

        $this->assertEquals($conversation->messages->count(), 1);
    }

    /** @test */
    public function it_returns_a_message_given_the_id()
    {
        $conversation = Chat::createConversation([$this->users[0], $this->users[1]]);

        $message = Chat::message('Hello')
            ->from($this->users[0])
            ->to($conversation)
            ->send();

        $m = Chat::messages()->getById($message->id);

        $this->assertEquals($message->id, $m->id);
    }

    /** @test */
    public function it_can_send_a_message_and_specificy_type()
    {
        $conversation = Chat::createConversation([$this->users[0], $this->users[1]]);

        $message = Chat::message('http://example.com/my-cool-image')
            ->type('image')
            ->from($this->users[0])
            ->to($conversation)
            ->send();

        $this->assertEquals('image', $message->type);
    }

    /** @test */
    public function it_can_mark_a_message_as_read()
    {
        $conversation = Chat::createConversation([$this->users[0], $this->users[1]]);

        $message = Chat::message('Hello there 0')
            ->from($this->users[1])
            ->to($conversation)
            ->send();

        Chat::message($message)->setParticipant($this->users[0])->markRead();

        $this->assertNotNull($message->getNotification($this->users[0])->read_at);
    }

    /** @test */
    public function it_can_delete_a_message()
    {
        $conversation = Chat::createConversation([$this->users[0], $this->users[1]]);
        $message = Chat::message('Hello there 0')->from($this->users[0])->to($conversation)->send();

        $messageId = 1;
        $perPage = 5;
        $page = 1;

        Chat::message($message)->setParticipant($this->users[1])->delete();

        $messages = Chat::conversation($conversation)->setParticipant($this->users[1])->getMessages($perPage, $page);

        $this->assertEquals(0, $messages->count());
    }

    /** @test */
    public function it_can_list_deleted_messages()
    {
        $conversation = Chat::createConversation([$this->users[0], $this->users[1]]);
        $message = Chat::message('Hello there 0')->from($this->users[0])->to($conversation)->send();

        $perPage = 5;
        $page = 1;

        Chat::message($message)->setParticipant($this->users[1])->delete();

        $messages = Chat::conversation($conversation)
            ->setParticipant($this->users[1])
            ->deleted()
            ->getMessages($perPage, $page);

        $this->assertEquals(1, $messages->count());
    }

    /** @test */
    public function it_can_tell_message_sender_participation()
    {
        /** @var Conversation $conversation */
        $conversation = Chat::createConversation([$this->users[0], $this->users[1]]);

        Chat::message('Hello')->from($this->users[0])->to($conversation)->send();

        $this->assertEquals(
            $conversation->messages[0]->participation->getKey(),
            $conversation->messages[0]->participation_id
        );
    }

    /** @test */
    public function it_can_tell_message_sender()
    {
        $bot = factory(Bot::class)->create();
        $client = factory(Client::class)->create();

        $conversation = Chat::createConversation([$this->users[0], $client, $bot]);
        Chat::message('Hello')->from($this->users[0])->to($conversation)->send();
        Chat::message('Hello')->from($bot)->to($conversation)->send();
        Chat::message('Hello')->from($client)->to($conversation)->send();

        $this->assertInstanceOf(User::class, $conversation->messages[0]->sender);
        $this->assertInstanceOf(Bot::class, $conversation->messages[1]->sender);
        $this->assertInstanceOf(Client::class, $conversation->messages[2]->sender);
    }

    /** @test */
    public function it_can_return_paginated_messages_in_a_conversation()
    {
        $conversation = Chat::createConversation([$this->users[0], $this->users[1]]);

        for ($i = 0; $i < 3; $i++) {
            Chat::message('Hello '.$i)->from($this->users[0])->to($conversation)->send();
            Chat::message('Hello Man '.$i)->from($this->users[1])->to($conversation)->send();
        }

        Chat::message('Hello Man')->from($this->users[1])->to($conversation)->send();

//        $messages  = Chat::conversation($conversation)->setParticipant($this->users[0])->perPage(3)->page(3)->getMessages();
//
//        dd($messages->toArray());
//
//        dd(Participation::first()->messageable);

        $this->assertEquals($conversation->messages->count(), 7);
        $this->assertEquals(3, Chat::conversation($conversation)->setParticipant($this->users[0])->perPage(3)->getMessages()->count());
        $this->assertEquals(3, Chat::conversation($conversation)->setParticipant($this->users[0])->perPage(3)->page(2)->getMessages()->count());
        $this->assertEquals(1, Chat::conversation($conversation)->setParticipant($this->users[0])->perPage(3)->page(3)->getMessages()->count());
        $this->assertEquals(0, Chat::conversation($conversation)->setParticipant($this->users[0])->perPage(3)->page(4)->getMessages()->count());
    }

    /** @test */
    public function it_can_return_recent_user_messsages()
    {
        $conversation = Chat::createConversation([$this->users[0], $this->users[1]]);
        Chat::message('Hello 1')->from($this->users[1])->to($conversation)->send();
        Chat::message('Hello 2')->from($this->users[0])->to($conversation)->send();

        $conversation2 = Chat::createConversation([$this->users[0], $this->users[2]]);
        Chat::message('Hello Man 4')->from($this->users[0])->to($conversation2)->send();

        $conversation3 = Chat::createConversation([$this->users[0], $this->users[3]]);
        Chat::message('Hello Man 5')->from($this->users[3])->to($conversation3)->send();
        Chat::message('Hello Man 6')->from($this->users[0])->to($conversation3)->send();
        Chat::message('Hello Man 3')->from($this->users[2])->to($conversation2)->send();
        Chat::message('Hello Man 10')->from($this->users[0])->to($conversation2)->send();

        $recent_messages = Chat::conversations()->setParticipant($this->users[0])->limit(5)->page(1)->get();
        $this->assertCount(3, $recent_messages);

        $recent_messages = Chat::conversations()->setParticipant($this->users[0])->setPaginationParams([
            'perPage'  => 1,
            'page'     => 1,
            'pageName' => 'test',
            'sorting'  => 'desc',
        ])->get();

        $this->assertCount(1, $recent_messages);
    }

    /** @test */
    public function it_return_unread_messages_count_for_user()
    {
        $conversation = Chat::createConversation([$this->users[0], $this->users[1]]);
        Chat::message('Hello 1')->from($this->users[1])->to($conversation)->send();
        Chat::message('Hello 2')->from($this->users[0])->to($conversation)->send();
        $message = Chat::message('Hello 2')->from($this->users[0])->to($conversation)->send();

        $this->assertEquals(2, Chat::messages()->setParticipant($this->users[1])->unreadCount());
        $this->assertEquals(1, Chat::messages()->setParticipant($this->users[0])->unreadCount());

        Chat::message($message)->setParticipant($this->users[1])->markRead();

        $this->assertEquals(1, Chat::messages()->setParticipant($this->users[1])->unreadCount());
    }

    /** @test */
    public function it_gets_a_message_by_id()
    {
        $conversation = Chat::createConversation([$this->users[0], $this->users[1]]);
        Chat::message('Hello 1')->from($this->users[1])->to($conversation)->send();
        $message = Chat::messages()->getById(1);

        $this->assertInstanceOf(Message::class, $message);
        $this->assertEquals(1, $message->id);
    }

    /** @test */
    public function it_flags_a_message()
    {
        $conversation = Chat::createConversation([$this->users[0], $this->users[1]]);
        $message = Chat::message('Hello')
            ->from($this->users[0])
            ->to($conversation)
            ->send();

        Chat::message($message)->setParticipant($this->users[1])->toggleFlag();
        $this->assertTrue(Chat::message($message)->setParticipant($this->users[1])->flagged());

        Chat::message($message)->setParticipant($this->users[1])->toggleFlag();
        $this->assertFalse(Chat::message($message)->setParticipant($this->users[1])->flagged());
    }
}
