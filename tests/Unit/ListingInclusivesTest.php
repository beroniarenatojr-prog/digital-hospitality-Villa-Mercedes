<?php

namespace Tests\Unit;

use App\Support\ListingInclusives;
use PHPUnit\Framework\TestCase;

class ListingInclusivesTest extends TestCase
{
    public function test_rooms_and_packages_each_have_six_inclusives(): void
    {
        $this->assertCount(6, ListingInclusives::rooms());
        $this->assertCount(6, ListingInclusives::packages());
        $this->assertSame(ListingInclusives::canonicalRooms(), ListingInclusives::for('rooms'));
        $this->assertSame(ListingInclusives::packages(), ListingInclusives::for('packages'));
        $this->assertSame(ListingInclusives::SHARED_ROOM_AMENITY_NAMES, array_column(ListingInclusives::rooms(), 'title'));
        $this->assertSame([
            'Fibre, resort-wide',
            'In every guest room',
            'In every bathroom',
            'Provided for your stay',
            'Ready in-room',
            'Three spring pools',
        ], array_column(ListingInclusives::rooms(), 'detail'));
        $this->assertNotContains('Bottled Water', array_column(ListingInclusives::rooms(), 'title'));
    }
}
