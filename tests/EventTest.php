<?php

namespace go1\app\tests;

use go1\app\App;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class EventTest extends TestCase
{
    public function testEventListener()
    {
        $triggered = false;

        $app = new App([
            'events' => [
                'foo' => function (Event $event) use (&$triggered) {
                    $triggered = ($event instanceof Event);
                },
            ],
        ]);

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $app['dispatcher'];
        $dispatcher->dispatch('foo');

        $this->assertTrue($triggered, 'Our listener is triggered.');
    }
}
