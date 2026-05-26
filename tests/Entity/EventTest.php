<?php

namespace App\Tests\Entity;

use App\Entity\Event;
use PHPUnit\Framework\TestCase;

class EventTest extends TestCase
{
    public function testSetAndGetTitre(): void
    {
        $event = new Event();
        $event->setTitre('Test Event');

        $this->assertSame('Test Event', $event->getTitre());
    }

    public function testStatus(): void
    {
        $event = new Event();
        $event->setStatus('Validé');

        $this->assertSame('Validé', $event->getStatus());
    }

    public function testDateEvent(): void
    {
        $event = new Event();
        $date = new \DateTime('2025-10-10');
        $event->setDateEvent($date);

        $this->assertSame($date, $event->getDateEvent());
    }
}
