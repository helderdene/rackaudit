<?php

namespace App\Enums;

/**
 * Room type classification for datacenter spaces.
 *
 * Defines the different types of rooms that can exist within a datacenter,
 * each serving a specific purpose in the facility's infrastructure.
 */
enum RoomType: string
{
    case ServerRoom = 'server_room';
    case NetworkCloset = 'network_closet';
    case CageColocation = 'cage_colocation';
    case Storage = 'storage';
    case ElectricalRoom = 'electrical_room';

    /**
     * Get the human-readable label for the room type.
     */
    public function label(): string
    {
        return match ($this) {
            self::ServerRoom => 'Server Room',
            self::NetworkCloset => 'Network Closet',
            self::CageColocation => 'Cage/Colocation Space',
            self::Storage => 'Storage',
            self::ElectricalRoom => 'Electrical Room',
        };
    }
}
