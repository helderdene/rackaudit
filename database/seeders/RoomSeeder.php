<?php

namespace Database\Seeders;

use App\Enums\PduPhase;
use App\Enums\PduStatus;
use App\Enums\RoomType;
use App\Enums\RowOrientation;
use App\Enums\RowStatus;
use App\Models\Datacenter;
use App\Models\Pdu;
use App\Models\Room;
use App\Models\Row;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    /**
     * Realistic room configurations for datacenters.
     */
    private array $roomTemplates = [
        [
            'name' => 'Main Server Hall A',
            'type' => RoomType::ServerRoom,
            'square_footage' => 15000,
            'description' => 'Primary server room with high-density compute infrastructure.',
            'rows' => [
                ['name' => 'Row A1', 'orientation' => RowOrientation::ColdAisle],
                ['name' => 'Row A2', 'orientation' => RowOrientation::HotAisle],
                ['name' => 'Row A3', 'orientation' => RowOrientation::ColdAisle],
                ['name' => 'Row A4', 'orientation' => RowOrientation::HotAisle],
                ['name' => 'Row A5', 'orientation' => RowOrientation::ColdAisle],
                ['name' => 'Row A6', 'orientation' => RowOrientation::HotAisle],
            ],
            'pdus' => [
                ['name' => 'PDU-A-01', 'model' => 'APC Symmetra PX', 'manufacturer' => 'APC by Schneider Electric', 'capacity' => 100.0, 'voltage' => 480, 'phase' => PduPhase::ThreePhase, 'circuits' => 42],
                ['name' => 'PDU-A-02', 'model' => 'APC Symmetra PX', 'manufacturer' => 'APC by Schneider Electric', 'capacity' => 100.0, 'voltage' => 480, 'phase' => PduPhase::ThreePhase, 'circuits' => 42],
            ],
        ],
        [
            'name' => 'Main Server Hall B',
            'type' => RoomType::ServerRoom,
            'square_footage' => 12000,
            'description' => 'Secondary server room for overflow and redundancy.',
            'rows' => [
                ['name' => 'Row B1', 'orientation' => RowOrientation::ColdAisle],
                ['name' => 'Row B2', 'orientation' => RowOrientation::HotAisle],
                ['name' => 'Row B3', 'orientation' => RowOrientation::ColdAisle],
                ['name' => 'Row B4', 'orientation' => RowOrientation::HotAisle],
            ],
            'pdus' => [
                ['name' => 'PDU-B-01', 'model' => 'Eaton 93PM', 'manufacturer' => 'Eaton', 'capacity' => 80.0, 'voltage' => 480, 'phase' => PduPhase::ThreePhase, 'circuits' => 36],
                ['name' => 'PDU-B-02', 'model' => 'Eaton 93PM', 'manufacturer' => 'Eaton', 'capacity' => 80.0, 'voltage' => 480, 'phase' => PduPhase::ThreePhase, 'circuits' => 36],
            ],
        ],
        [
            'name' => 'Network Operations Center',
            'type' => RoomType::NetworkCloset,
            'square_footage' => 2500,
            'description' => 'Core network infrastructure and switching equipment.',
            'rows' => [
                ['name' => 'Network Row 1', 'orientation' => RowOrientation::ColdAisle],
                ['name' => 'Network Row 2', 'orientation' => RowOrientation::HotAisle],
            ],
            'pdus' => [
                ['name' => 'PDU-NET-01', 'model' => 'Vertiv Liebert GXT5', 'manufacturer' => 'Vertiv', 'capacity' => 30.0, 'voltage' => 208, 'phase' => PduPhase::Single, 'circuits' => 24],
            ],
        ],
        [
            'name' => 'Colocation Suite 1',
            'type' => RoomType::CageColocation,
            'square_footage' => 5000,
            'description' => 'Secure colocation space for enterprise clients.',
            'rows' => [
                ['name' => 'Cage 1-A', 'orientation' => RowOrientation::ColdAisle],
                ['name' => 'Cage 1-B', 'orientation' => RowOrientation::HotAisle],
                ['name' => 'Cage 1-C', 'orientation' => RowOrientation::ColdAisle],
            ],
            'pdus' => [
                ['name' => 'PDU-COLO1-01', 'model' => 'CyberPower OL10000RT3U', 'manufacturer' => 'CyberPower', 'capacity' => 50.0, 'voltage' => 208, 'phase' => PduPhase::ThreePhase, 'circuits' => 30],
            ],
        ],
        [
            'name' => 'Electrical Room MDF',
            'type' => RoomType::ElectricalRoom,
            'square_footage' => 3000,
            'description' => 'Main distribution facility for power infrastructure.',
            'rows' => [],
            'pdus' => [
                ['name' => 'MDF-PDU-01', 'model' => 'Schneider Galaxy VX', 'manufacturer' => 'Schneider Electric', 'capacity' => 500.0, 'voltage' => 480, 'phase' => PduPhase::ThreePhase, 'circuits' => 84],
                ['name' => 'MDF-PDU-02', 'model' => 'Schneider Galaxy VX', 'manufacturer' => 'Schneider Electric', 'capacity' => 500.0, 'voltage' => 480, 'phase' => PduPhase::ThreePhase, 'circuits' => 84],
            ],
        ],
        [
            'name' => 'Storage & Archives',
            'type' => RoomType::Storage,
            'square_footage' => 1500,
            'description' => 'Equipment storage and spare parts inventory.',
            'rows' => [],
            'pdus' => [],
        ],
    ];

    public function run(): void
    {
        $datacenters = Datacenter::all();

        foreach ($datacenters as $datacenter) {
            $this->seedRoomsForDatacenter($datacenter);
        }
    }

    private function seedRoomsForDatacenter(Datacenter $datacenter): void
    {
        // Skip if datacenter already has rooms
        if ($datacenter->rooms()->exists()) {
            return;
        }

        // Randomly select 3-5 room templates for variety
        $selectedTemplates = collect($this->roomTemplates)
            ->shuffle()
            ->take(rand(3, 5))
            ->values();

        foreach ($selectedTemplates as $template) {
            $room = Room::create([
                'datacenter_id' => $datacenter->id,
                'name' => $template['name'],
                'type' => $template['type'],
                'square_footage' => $template['square_footage'] * (rand(80, 120) / 100), // Vary by ±20%
                'description' => $template['description'],
            ]);

            // Create rows
            $position = 1;
            foreach ($template['rows'] as $rowData) {
                Row::create([
                    'room_id' => $room->id,
                    'name' => $rowData['name'],
                    'position' => $position++,
                    'orientation' => $rowData['orientation'],
                    'status' => rand(1, 10) > 1 ? RowStatus::Active : RowStatus::Inactive,
                ]);
            }

            // Create PDUs (assign to room level)
            foreach ($template['pdus'] as $pduData) {
                Pdu::create([
                    'room_id' => $room->id,
                    'row_id' => null,
                    'name' => $pduData['name'],
                    'model' => $pduData['model'],
                    'manufacturer' => $pduData['manufacturer'],
                    'total_capacity_kw' => $pduData['capacity'],
                    'voltage' => $pduData['voltage'],
                    'phase' => $pduData['phase'],
                    'circuit_count' => $pduData['circuits'],
                    'status' => $this->randomPduStatus(),
                ]);
            }

            // For rooms with rows, add some row-level PDUs
            $rows = $room->rows;
            if ($rows->count() > 0) {
                $rowPduCount = rand(1, min(3, $rows->count()));
                $selectedRows = $rows->random($rowPduCount);

                foreach ($selectedRows as $index => $row) {
                    Pdu::create([
                        'room_id' => null,
                        'row_id' => $row->id,
                        'name' => "RPP-{$row->name}-" . ($index + 1),
                        'model' => 'Raritan PX3',
                        'manufacturer' => 'Raritan',
                        'total_capacity_kw' => rand(20, 40) * 1.0,
                        'voltage' => 208,
                        'phase' => PduPhase::ThreePhase,
                        'circuit_count' => rand(12, 24),
                        'status' => $this->randomPduStatus(),
                    ]);
                }
            }
        }
    }

    private function randomPduStatus(): PduStatus
    {
        $rand = rand(1, 100);
        if ($rand <= 85) {
            return PduStatus::Active;
        } elseif ($rand <= 95) {
            return PduStatus::Maintenance;
        }

        return PduStatus::Inactive;
    }
}
