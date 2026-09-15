<?php

namespace Database\Seeders;

use App\Enums\GuestGender;
use App\Enums\GuestIdType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RateType;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoGuestSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@villamercedes.test')->first();
        $facilities = $this->facilities();

        Reservation::withoutEvents(function () use ($admin, $facilities): void {
            Payment::withoutEvents(function () use ($admin, $facilities): void {
                foreach ($this->guests() as $index => $guest) {
                    $user = $this->upsertGuest($guest);
                    $this->seedReservations($user, $guest['reservations'], $facilities, $admin, $index + 1);
                }
            });
        });
    }

    /**
     * @return list<array{name: string, email: string, phone: string, reservations: list<array<string, mixed>>}>
     */
    private function guests(): array
    {
        $today = now()->startOfDay();

        return [
            [
                'name' => 'Ana Reyes',
                'email' => 'ana.reyes@villamercedes.test',
                'phone' => '09171230001',
                'reservations' => [
                    [
                        'facility' => 'Superior (Room 01)',
                        'nights' => 2,
                        'check_in' => $today->copy()->addDays(5)->setTime(14, 0),
                        'guest_count' => 2,
                        'status' => ReservationStatus::Pending,
                        'source' => ReservationSource::Online,
                        'special_requests' => 'Late arrival around 7PM. Extra pillows please.',
                        'guest' => [
                            'place' => 'Ilagan, Isabela',
                            'nationality' => 'Filipino',
                            'id' => 'DL-ANA-1001',
                            'gender' => GuestGender::Female,
                            'companions' => ['Marco Reyes'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Ben Santos',
                'email' => 'ben.santos@villamercedes.test',
                'phone' => '09171230002',
                'reservations' => [
                    [
                        'facility' => 'Superior Family (Room 02)',
                        'nights' => 1,
                        'check_in' => $today->copy()->addDays(8)->setTime(14, 0),
                        'guest_count' => 4,
                        'status' => ReservationStatus::Pending,
                        'source' => ReservationSource::Online,
                        'special_requests' => 'Birthday stay. Cake delivery around 4PM.',
                        'payment' => [
                            'status' => PaymentStatus::Pending,
                            'method' => PaymentMethod::GCash,
                            'portion' => 0.5,
                            'reference' => 'GCASH-BEN-88021',
                        ],
                        'guest' => [
                            'place' => 'Cauayan, Isabela',
                            'nationality' => 'Filipino',
                            'company' => 'Santos Trading',
                            'id' => 'UMID-BEN-2002',
                            'gender' => GuestGender::Male,
                            'companions' => ['Lara Santos', 'Mia Santos', 'Noah Santos'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Carla Dela Cruz',
                'email' => 'carla.delacruz@villamercedes.test',
                'phone' => '09171230003',
                'reservations' => [
                    [
                        'facility' => 'Superior (Room 3)',
                        'nights' => 2,
                        'check_in' => $today->copy()->addDays(3)->setTime(14, 0),
                        'guest_count' => 2,
                        'status' => ReservationStatus::Approved,
                        'source' => ReservationSource::Online,
                        'approved_at' => now()->subDay(),
                        'early_check_in_hours' => 2,
                        'payment' => [
                            'status' => PaymentStatus::Verified,
                            'method' => PaymentMethod::BankTransfer,
                            'portion' => 1,
                            'reference' => 'BPI-CARLA-33109',
                        ],
                        'guest' => [
                            'place' => 'Tuguegarao, Cagayan',
                            'nationality' => 'Filipino',
                            'id' => 'PHILID-CARLA-3003',
                            'gender' => GuestGender::Female,
                            'companions' => ['Paolo Dela Cruz'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Diego Ramos',
                'email' => 'diego.ramos@villamercedes.test',
                'phone' => '09171230004',
                'reservations' => [
                    [
                        'facility' => 'Deluxe (Room 26)',
                        'nights' => 1,
                        'check_in' => $today->copy()->setTime(14, 0),
                        'guest_count' => 2,
                        'status' => ReservationStatus::Approved,
                        'source' => ReservationSource::Online,
                        'approved_at' => now()->subDays(2),
                        'special_requests' => 'Arriving this afternoon from Santiago.',
                        'payment' => [
                            'status' => PaymentStatus::Verified,
                            'method' => PaymentMethod::Maya,
                            'portion' => 0.5,
                            'reference' => 'MAYA-DIEGO-44110',
                        ],
                        'guest' => [
                            'place' => 'Santiago, Isabela',
                            'nationality' => 'Filipino',
                            'id' => 'DL-DIEGO-4004',
                            'gender' => GuestGender::Male,
                            'companions' => ['Sofia Ramos'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Elena Garcia',
                'email' => 'elena.garcia@villamercedes.test',
                'phone' => '09171230005',
                'reservations' => [
                    [
                        'facility' => 'Superior Family (Room 17)',
                        'nights' => 2,
                        'check_in' => $today->copy()->subDay()->setTime(14, 0),
                        'guest_count' => 5,
                        'status' => ReservationStatus::Approved,
                        'source' => ReservationSource::Online,
                        'approved_at' => now()->subDays(4),
                        'checked_in_at' => $today->copy()->subDay()->setTime(14, 20),
                        'late_check_out_hours' => 2,
                        'admin_notes' => 'Family already on property. Late checkout requested.',
                        'payment' => [
                            'status' => PaymentStatus::Verified,
                            'method' => PaymentMethod::Cash,
                            'portion' => 1,
                            'reference' => 'CASH-ELENA-55001',
                        ],
                        'guest' => [
                            'place' => 'Quezon City',
                            'nationality' => 'Filipino',
                            'company' => 'Garcia Family Reunion',
                            'id' => 'PASSPORT-P8123456',
                            'gender' => GuestGender::Female,
                            'companions' => ['Luis Garcia', 'Tessa Garcia', 'Rico Garcia', 'Nina Garcia'],
                            'signed' => true,
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Francis Tan',
                'email' => 'francis.tan@villamercedes.test',
                'phone' => '09171230006',
                'reservations' => [
                    [
                        'facility' => 'Superior Barkada (Room 10)',
                        'nights' => 2,
                        'check_in' => $today->copy()->addDays(10)->setTime(14, 0),
                        'guest_count' => 6,
                        'status' => ReservationStatus::Approved,
                        'source' => ReservationSource::Online,
                        'approved_at' => now()->subDays(3),
                        'cancellation_requested_at' => now()->subHours(6),
                        'cancellation_request_reason' => 'Office retreat was moved to next month.',
                        'payment' => [
                            'status' => PaymentStatus::Verified,
                            'method' => PaymentMethod::GCash,
                            'portion' => 0.5,
                            'reference' => 'GCASH-FRANCIS-66012',
                        ],
                        'guest' => [
                            'place' => 'Makati City',
                            'nationality' => 'Filipino',
                            'company' => 'Tan & Co.',
                            'id' => 'UMID-FRANCIS-6006',
                            'gender' => GuestGender::Male,
                            'companions' => ['Kyle Tan', 'Owen Tan'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Gina Villanueva',
                'email' => 'gina.villanueva@villamercedes.test',
                'phone' => '09171230007',
                'reservations' => [
                    [
                        'facility' => 'Emerald Hall',
                        'nights' => 1,
                        'check_in' => $today->copy()->addDays(14)->setTime(8, 0),
                        'check_out' => $today->copy()->addDays(14)->setTime(17, 0),
                        'guest_count' => 80,
                        'status' => ReservationStatus::Rejected,
                        'source' => ReservationSource::Online,
                        'rejected_at' => now()->subDays(2),
                        'rejection_reason' => 'Hall is already committed to another event on that date.',
                        'guest' => [
                            'place' => 'Ilagan, Isabela',
                            'nationality' => 'Filipino',
                            'company' => 'Villanueva Events',
                            'id' => 'PRC-GINA-7007',
                            'gender' => GuestGender::Female,
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Hiroshi Tanaka',
                'email' => 'hiroshi.tanaka@villamercedes.test',
                'phone' => '09171230008',
                'reservations' => [
                    [
                        'facility' => 'Deluxe (Room 27)',
                        'nights' => 3,
                        'check_in' => $today->copy()->addDays(6)->setTime(14, 0),
                        'guest_count' => 2,
                        'status' => ReservationStatus::Cancelled,
                        'source' => ReservationSource::Online,
                        'approved_at' => now()->subDays(5),
                        'cancelled_at' => now()->subDay(),
                        'cancellation_reason' => 'Guest cancelled after itinerary change.',
                        'guest' => [
                            'place' => 'Tokyo, Japan',
                            'nationality' => 'Japanese',
                            'id' => 'PASSPORT-TR998877',
                            'gender' => GuestGender::Male,
                            'companions' => ['Aiko Tanaka'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Isabel Mendoza',
                'email' => 'isabel.mendoza@villamercedes.test',
                'phone' => '09171230009',
                'reservations' => [
                    [
                        'facility' => 'Standard Family (Room 21)',
                        'nights' => 2,
                        'check_in' => $today->copy()->subDays(10)->setTime(14, 0),
                        'guest_count' => 4,
                        'status' => ReservationStatus::Completed,
                        'source' => ReservationSource::Online,
                        'approved_at' => now()->subDays(18),
                        'checked_in_at' => $today->copy()->subDays(10)->setTime(14, 10),
                        'checked_out_at' => $today->copy()->subDays(8)->setTime(11, 45),
                        'completed_at' => $today->copy()->subDays(8)->setTime(11, 50),
                        'payment' => [
                            'status' => PaymentStatus::Verified,
                            'method' => PaymentMethod::BankTransfer,
                            'portion' => 1,
                            'reference' => 'BDO-ISABEL-99001',
                        ],
                        'guest' => [
                            'place' => 'Cabanatuan, Nueva Ecija',
                            'nationality' => 'Filipino',
                            'id' => 'PHILID-ISABEL-9009',
                            'gender' => GuestGender::Female,
                            'companions' => ['Ramon Mendoza', 'Clara Mendoza', 'Leo Mendoza'],
                            'signed' => true,
                        ],
                    ],
                    [
                        'facility' => 'Deluxe (Room 28)',
                        'nights' => 1,
                        'check_in' => $today->copy()->addDays(21)->setTime(14, 0),
                        'guest_count' => 2,
                        'status' => ReservationStatus::Pending,
                        'source' => ReservationSource::Online,
                        'special_requests' => 'Returning guest. Same room type as last stay if possible.',
                        'guest' => [
                            'place' => 'Cabanatuan, Nueva Ecija',
                            'nationality' => 'Filipino',
                            'id' => 'PHILID-ISABEL-9009',
                            'gender' => GuestGender::Female,
                            'companions' => ['Ramon Mendoza'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Jose Aquino',
                'email' => 'jose.aquino@villamercedes.test',
                'phone' => '09171230010',
                'reservations' => [
                    [
                        'facility' => 'Sapphire Hall',
                        'nights' => 1,
                        'check_in' => $today->copy()->addDays(18)->setTime(8, 0),
                        'check_out' => $today->copy()->addDays(18)->setTime(22, 0),
                        'guest_count' => 90,
                        'status' => ReservationStatus::Approved,
                        'source' => ReservationSource::WalkIn,
                        'approved_at' => now()->subHours(8),
                        'admin_notes' => 'Walk-in event inquiry encoded at the front desk.',
                        'payment' => [
                            'status' => PaymentStatus::Rejected,
                            'method' => PaymentMethod::GCash,
                            'portion' => 0.3,
                            'reference' => 'GCASH-JOSE-BAD',
                            'rejection_reason' => 'Reference number does not match the GCash receipt.',
                        ],
                        'guest' => [
                            'place' => 'Ilagan, Isabela',
                            'nationality' => 'Filipino',
                            'company' => 'Aquino Alumni Association',
                            'id' => 'UMID-JOSE-1010',
                            'gender' => GuestGender::Male,
                        ],
                    ],
                    [
                        'facility' => 'Dorm (A)',
                        'nights' => 1,
                        'check_in' => $today->copy()->addDays(12)->setTime(14, 0),
                        'guest_count' => 18,
                        'status' => ReservationStatus::Pending,
                        'source' => ReservationSource::Online,
                        'special_requests' => 'Church youth camp. Need extra extra mattresses if available.',
                        'payment' => [
                            'status' => PaymentStatus::Pending,
                            'method' => PaymentMethod::Maya,
                            'portion' => 0.5,
                            'reference' => 'MAYA-JOSE-10188',
                        ],
                        'guest' => [
                            'place' => 'Ilagan, Isabela',
                            'nationality' => 'Filipino',
                            'company' => 'San Antonio Youth Ministry',
                            'id' => 'UMID-JOSE-1010',
                            'gender' => GuestGender::Male,
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Katrina Lopez',
                'email' => 'katrina.lopez@villamercedes.test',
                'phone' => '09171230011',
                'reservations' => [
                    [
                        'facility' => 'Deluxe Barkada (Room 13)',
                        'nights' => 2,
                        'check_in' => $today->copy()->addDays(7)->setTime(14, 0),
                        'guest_count' => 8,
                        'status' => ReservationStatus::Pending,
                        'source' => ReservationSource::Online,
                        'special_requests' => 'Barkada reunion. Need extra towels and a late-night merienda table.',
                        'guest' => [
                            'place' => 'Roxas, Isabela',
                            'nationality' => 'Filipino',
                            'id' => 'DL-KATRINA-1111',
                            'gender' => GuestGender::Female,
                            'companions' => ['Bea Lopez', 'Cindy Lopez', 'Dana Lopez'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Luis Navarro',
                'email' => 'luis.navarro@villamercedes.test',
                'phone' => '09171230012',
                'reservations' => [
                    [
                        'facility' => 'Superior (Room 4)',
                        'nights' => 1,
                        'check_in' => $today->copy()->setTime(14, 0),
                        'guest_count' => 2,
                        'status' => ReservationStatus::Approved,
                        'source' => ReservationSource::Online,
                        'approved_at' => now()->subDays(3),
                        'special_requests' => 'Second arrival today. Guest asked for a ground-floor room.',
                        'payment' => [
                            'status' => PaymentStatus::Verified,
                            'method' => PaymentMethod::GCash,
                            'portion' => 1,
                            'reference' => 'GCASH-LUIS-12012',
                        ],
                        'guest' => [
                            'place' => 'Alicia, Isabela',
                            'nationality' => 'Filipino',
                            'id' => 'UMID-LUIS-1212',
                            'gender' => GuestGender::Male,
                            'companions' => ['Hannah Navarro'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Maria Cruz',
                'email' => 'maria.cruz@villamercedes.test',
                'phone' => '09171230013',
                'reservations' => [
                    [
                        'facility' => 'Deluxe Barkada (Room 14)',
                        'nights' => 1,
                        'check_in' => $today->copy()->setTime(10, 0),
                        'guest_count' => 6,
                        'status' => ReservationStatus::Approved,
                        'source' => ReservationSource::WalkIn,
                        'approved_at' => $today->copy()->setTime(9, 40),
                        'checked_in_at' => $today->copy()->setTime(10, 15),
                        'early_check_in_hours' => 4,
                        'admin_notes' => 'Walk-in this morning. Already occupying the room.',
                        'payment' => [
                            'status' => PaymentStatus::Pending,
                            'method' => PaymentMethod::Cash,
                            'portion' => 1,
                            'reference' => 'CASH-MARIA-13013',
                        ],
                        'guest' => [
                            'place' => 'Ilagan, Isabela',
                            'nationality' => 'Filipino',
                            'company' => 'Cruz Catering',
                            'id' => 'PHILID-MARIA-1313',
                            'gender' => GuestGender::Female,
                            'companions' => ['Anton Cruz', 'Liza Cruz', 'Gino Cruz'],
                            'signed' => true,
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Nico Bautista',
                'email' => 'nico.bautista@villamercedes.test',
                'phone' => '09171230014',
                'reservations' => [
                    [
                        'facility' => 'Deluxe (Room 29)',
                        'nights' => 1,
                        'check_in' => $today->copy()->subDay()->setTime(14, 0),
                        'guest_count' => 2,
                        'status' => ReservationStatus::Approved,
                        'source' => ReservationSource::Online,
                        'approved_at' => now()->subDays(6),
                        'checked_in_at' => $today->copy()->subDay()->setTime(14, 5),
                        'admin_notes' => 'Due for checkout today at noon.',
                        'payment' => [
                            'status' => PaymentStatus::Verified,
                            'method' => PaymentMethod::Maya,
                            'portion' => 1,
                            'reference' => 'MAYA-NICO-14014',
                        ],
                        'guest' => [
                            'place' => 'Bambang, Nueva Vizcaya',
                            'nationality' => 'Filipino',
                            'id' => 'DL-NICO-1414',
                            'gender' => GuestGender::Male,
                            'companions' => ['Ellen Bautista'],
                            'signed' => true,
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Olivia Fernandez',
                'email' => 'olivia.fernandez@villamercedes.test',
                'phone' => '09171230015',
                'reservations' => [
                    [
                        'facility' => 'Overnight Package',
                        'nights' => 1,
                        'check_in' => $today->copy()->addDays(4)->setTime(14, 0),
                        'guest_count' => 4,
                        'status' => ReservationStatus::Pending,
                        'source' => ReservationSource::Online,
                        'special_requests' => 'First time booking a package. Please confirm breakfast time.',
                        'payment' => [
                            'status' => PaymentStatus::Pending,
                            'method' => PaymentMethod::BankTransfer,
                            'portion' => 0.5,
                            'reference' => 'BDO-OLIVIA-15015',
                        ],
                        'guest' => [
                            'place' => 'Echague, Isabela',
                            'nationality' => 'Filipino',
                            'id' => 'UMID-OLIVIA-1515',
                            'gender' => GuestGender::Female,
                            'companions' => ['Marco Fernandez', 'Pia Fernandez'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Paolo Gutierrez',
                'email' => 'paolo.gutierrez@villamercedes.test',
                'phone' => '09171230016',
                'reservations' => [
                    [
                        'facility' => 'Diamond Hall',
                        'nights' => 1,
                        'check_in' => $today->copy()->addDays(25)->setTime(8, 0),
                        'check_out' => $today->copy()->addDays(25)->setTime(22, 0),
                        'guest_count' => 180,
                        'status' => ReservationStatus::Pending,
                        'source' => ReservationSource::Online,
                        'special_requests' => 'Wedding reception. Need round tables and a bridal room nearby.',
                        'payment' => [
                            'status' => PaymentStatus::Pending,
                            'method' => PaymentMethod::BankTransfer,
                            'portion' => 0.3,
                            'reference' => 'BPI-PAOLO-16016',
                        ],
                        'guest' => [
                            'place' => 'Cauayan, Isabela',
                            'nationality' => 'Filipino',
                            'company' => 'Gutierrez-Reyes Nuptials',
                            'id' => 'PHILID-PAOLO-1616',
                            'gender' => GuestGender::Male,
                            'companions' => ['Andrea Reyes'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Queenie Lim',
                'email' => 'queenie.lim@villamercedes.test',
                'phone' => '09171230017',
                'reservations' => [
                    [
                        'facility' => 'Opal Hall',
                        'nights' => 1,
                        'check_in' => $today->copy()->subDays(56)->setTime(8, 0),
                        'check_out' => $today->copy()->subDays(56)->setTime(17, 0),
                        'guest_count' => 70,
                        'status' => ReservationStatus::Completed,
                        'source' => ReservationSource::Online,
                        'approved_at' => now()->subDays(70),
                        'checked_in_at' => $today->copy()->subDays(56)->setTime(8, 10),
                        'checked_out_at' => $today->copy()->subDays(56)->setTime(17, 5),
                        'completed_at' => $today->copy()->subDays(56)->setTime(17, 10),
                        'admin_notes' => 'Lim Logistics seminar completed.',
                        'payment' => [
                            'status' => PaymentStatus::Verified,
                            'method' => PaymentMethod::BankTransfer,
                            'portion' => 1,
                            'reference' => 'UBP-QUEENIE-17016',
                        ],
                        'guest' => [
                            'place' => 'Tuguegarao, Cagayan',
                            'nationality' => 'Filipino',
                            'company' => 'Lim Logistics',
                            'id' => 'PRC-QUEENIE-1717',
                            'gender' => GuestGender::Female,
                        ],
                    ],
                    [
                        'facility' => 'Opal Hall',
                        'nights' => 1,
                        'check_in' => $today->copy()->addDays(9)->setTime(8, 0),
                        'check_out' => $today->copy()->addDays(9)->setTime(17, 0),
                        'guest_count' => 70,
                        'status' => ReservationStatus::Approved,
                        'source' => ReservationSource::Online,
                        'approved_at' => now()->subDays(2),
                        'admin_notes' => 'Company seminar. Fully paid.',
                        'payment' => [
                            'status' => PaymentStatus::Verified,
                            'method' => PaymentMethod::BankTransfer,
                            'portion' => 1,
                            'reference' => 'UBP-QUEENIE-17017',
                        ],
                        'guest' => [
                            'place' => 'Tuguegarao, Cagayan',
                            'nationality' => 'Filipino',
                            'company' => 'Lim Logistics',
                            'id' => 'PRC-QUEENIE-1717',
                            'gender' => GuestGender::Female,
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Rafael Sy',
                'email' => 'rafael.sy@villamercedes.test',
                'phone' => '09171230018',
                'reservations' => [
                    [
                        'facility' => 'Standard Family (Room 22)',
                        'nights' => 2,
                        'check_in' => $today->copy()->subDays(20)->setTime(14, 0),
                        'guest_count' => 4,
                        'status' => ReservationStatus::Completed,
                        'source' => ReservationSource::Online,
                        'approved_at' => now()->subDays(28),
                        'checked_in_at' => $today->copy()->subDays(20)->setTime(14, 5),
                        'checked_out_at' => $today->copy()->subDays(18)->setTime(11, 30),
                        'completed_at' => $today->copy()->subDays(18)->setTime(11, 40),
                        'payment' => [
                            'status' => PaymentStatus::Verified,
                            'method' => PaymentMethod::Cash,
                            'portion' => 1,
                            'reference' => 'CASH-RAFAEL-18018',
                        ],
                        'guest' => [
                            'place' => 'Manila',
                            'nationality' => 'Filipino',
                            'id' => 'PASSPORT-P2233445',
                            'gender' => GuestGender::Male,
                            'companions' => ['Grace Sy', 'Ian Sy', 'May Sy'],
                            'signed' => true,
                        ],
                    ],
                    [
                        'facility' => 'Superior Barkada (Room 16)',
                        'nights' => 2,
                        'check_in' => $today->copy()->addDays(16)->setTime(14, 0),
                        'guest_count' => 8,
                        'status' => ReservationStatus::Rejected,
                        'source' => ReservationSource::Online,
                        'rejected_at' => now()->subHours(20),
                        'rejection_reason' => 'Requested dates conflict with a blocked maintenance window.',
                        'guest' => [
                            'place' => 'Manila',
                            'nationality' => 'Filipino',
                            'id' => 'PASSPORT-P2233445',
                            'gender' => GuestGender::Male,
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Sofia Park',
                'email' => 'sofia.park@villamercedes.test',
                'phone' => '09171230019',
                'reservations' => [
                    [
                        'facility' => 'Dorm (B)',
                        'nights' => 2,
                        'check_in' => $today->copy()->addDays(11)->setTime(14, 0),
                        'guest_count' => 16,
                        'status' => ReservationStatus::Approved,
                        'source' => ReservationSource::Online,
                        'approved_at' => now()->subDays(4),
                        'cancellation_requested_at' => now()->subHours(3),
                        'cancellation_request_reason' => 'School trip was postponed by the principal.',
                        'payment' => [
                            'status' => PaymentStatus::Verified,
                            'method' => PaymentMethod::GCash,
                            'portion' => 0.5,
                            'reference' => 'GCASH-SOFIA-19019',
                        ],
                        'guest' => [
                            'place' => 'Seoul, South Korea',
                            'nationality' => 'Korean',
                            'company' => 'Isabela Korean Language Camp',
                            'id' => 'PASSPORT-M7788990',
                            'gender' => GuestGender::Female,
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Tomas Villar',
                'email' => 'tomas.villar@villamercedes.test',
                'phone' => '09171230020',
                'reservations' => [
                    [
                        'facility' => 'Standard Barkada (Room 11)',
                        'nights' => 2,
                        'check_in' => $today->copy()->addDays(6)->setTime(14, 0),
                        'guest_count' => 10,
                        'status' => ReservationStatus::Cancelled,
                        'source' => ReservationSource::Online,
                        'approved_at' => now()->subDays(8),
                        'cancelled_at' => now()->subHours(18),
                        'cancellation_reason' => 'Guest cancelled after the deposit was already verified.',
                        'payment' => [
                            'status' => PaymentStatus::Verified,
                            'method' => PaymentMethod::Maya,
                            'portion' => 0.5,
                            'reference' => 'MAYA-TOMAS-20020',
                        ],
                        'guest' => [
                            'place' => 'San Mateo, Isabela',
                            'nationality' => 'Filipino',
                            'company' => 'Villar Construction',
                            'id' => 'UMID-TOMAS-2020',
                            'gender' => GuestGender::Male,
                        ],
                    ],
                    [
                        'facility' => 'Superior (Room 5)',
                        'nights' => 3,
                        'check_in' => $today->copy()->addDays(15)->setTime(14, 0),
                        'guest_count' => 2,
                        'status' => ReservationStatus::Pending,
                        'source' => ReservationSource::Online,
                        'special_requests' => 'Rebooking after the barkada trip was cancelled.',
                        'guest' => [
                            'place' => 'San Mateo, Isabela',
                            'nationality' => 'Filipino',
                            'id' => 'UMID-TOMAS-2020',
                            'gender' => GuestGender::Male,
                            'companions' => ['Rosa Villar'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array{name: string, email: string, phone: string}  $guest
     */
    private function upsertGuest(array $guest): User
    {
        $sheet = $guest['reservations'][0]['guest'] ?? [];
        $gender = $sheet['gender'] ?? null;

        $user = User::query()->firstOrCreate(
            ['email' => $guest['email']],
            [
                'name' => $guest['name'],
                'phone' => $guest['phone'],
                'gender' => $gender instanceof GuestGender ? $gender->value : $gender,
                'address' => $sheet['place'] ?? null,
                'nationality' => $sheet['nationality'] ?? null,
                'password' => Hash::make('Password123!'),
                'email_verified_at' => now(),
                'profile_completed_at' => now(),
            ]
        );

        $user->fill([
            'name' => $guest['name'],
            'phone' => $guest['phone'],
            'gender' => $gender instanceof GuestGender ? $gender->value : ($gender ?? $user->gender),
            'address' => $sheet['place'] ?? $user->address,
            'nationality' => $sheet['nationality'] ?? $user->nationality,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'profile_completed_at' => $user->profile_completed_at ?? now(),
        ])->save();

        if (! $user->hasRole(Role::Customer->value)) {
            $user->assignRole(Role::Customer->value);
        }

        return $user->fresh();
    }

    /**
     * @param  list<array<string, mixed>>  $reservations
     * @param  array<string, Facility>  $facilities
     */
    private function seedReservations(User $user, array $reservations, array $facilities, ?User $admin, int $guestNumber): void
    {
        foreach ($reservations as $offset => $payload) {
            $facility = $facilities[$payload['facility']] ?? null;

            if (! $facility) {
                $this->command?->warn("Skipped {$payload['facility']} for {$user->email}: facility not found.");

                continue;
            }

            $checkIn = $payload['check_in'];
            $checkOut = $payload['check_out'] ?? $checkIn->copy()->addDays($payload['nights'])->setTime(12, 0);
            $nights = max(1, (int) $checkIn->copy()->startOfDay()->diffInDays($checkOut->copy()->startOfDay()) ?: ($payload['nights'] ?? 1));
            $basePrice = $this->basePrice($facility, $nights);
            $additional = ((int) ($payload['early_check_in_hours'] ?? 0) * 200)
                + ((int) ($payload['late_check_out_hours'] ?? 0) * 300);
            $total = $basePrice + $additional;
            $code = sprintf('VM-DEMO-%02d%s', $guestNumber, $offset === 0 ? '' : chr(65 + $offset));
            $guestSheet = $payload['guest'] ?? [];

            $reservation = Reservation::query()->updateOrCreate(
                ['reservation_code' => $code],
                [
                    'user_id' => $user->id,
                    'guest_name' => $user->name,
                    'guest_email' => $user->email,
                    'guest_phone' => $user->phone,
                    'guest_place_of_origin' => $guestSheet['place'] ?? null,
                    'guest_nationality' => $guestSheet['nationality'] ?? null,
                    'guest_company' => $guestSheet['company'] ?? null,
                    'guest_valid_id' => $this->guestIdTypeFromDemo($guestSheet['id'] ?? null),
                    'guest_gender' => $guestSheet['gender'] ?? null,
                    'facility_id' => $facility->id,
                    'reservation_date' => $checkIn->toDateString(),
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'guest_count' => $payload['guest_count'],
                    'other_guest_names' => $guestSheet['companions'] ?? [],
                    'early_check_in_hours' => $payload['early_check_in_hours'] ?? 0,
                    'late_check_out_hours' => $payload['late_check_out_hours'] ?? 0,
                    'special_requests' => $payload['special_requests'] ?? null,
                    'base_price' => $basePrice,
                    'additional_charges' => $additional,
                    'total_price' => $total,
                    'status' => $payload['status']->value,
                    'reservation_source' => $payload['source']->value,
                    'admin_notes' => $payload['admin_notes'] ?? null,
                    'approved_at' => $payload['approved_at'] ?? null,
                    'checked_in_at' => $payload['checked_in_at'] ?? null,
                    'checked_out_at' => $payload['checked_out_at'] ?? null,
                    'rejected_at' => $payload['rejected_at'] ?? null,
                    'cancelled_at' => $payload['cancelled_at'] ?? null,
                    'completed_at' => $payload['completed_at'] ?? null,
                    'rejection_reason' => $payload['rejection_reason'] ?? null,
                    'cancellation_reason' => $payload['cancellation_reason'] ?? null,
                    'cancellation_requested_at' => $payload['cancellation_requested_at'] ?? null,
                    'cancellation_request_reason' => $payload['cancellation_request_reason'] ?? null,
                ]
            );

            if (isset($payload['payment'])) {
                $this->upsertPayment($reservation, $payload['payment'], $total, $admin);
            }
        }
    }

    /**
     * @param  array{status: PaymentStatus, method: PaymentMethod, portion: float, reference: string, rejection_reason?: string}  $payment
     */
    private function upsertPayment(Reservation $reservation, array $payment, float $total, ?User $admin): void
    {
        $amount = round($total * $payment['portion'], 2);
        $verified = $payment['status'] === PaymentStatus::Verified;

        Payment::query()->updateOrCreate(
            [
                'reservation_id' => $reservation->id,
                'reference_number' => $payment['reference'],
            ],
            [
                'amount' => $amount,
                'method' => $payment['method']->value,
                'payment_date' => now()->subDay()->toDateString(),
                'status' => $payment['status']->value,
                'verified_by' => $verified ? $admin?->id : null,
                'verified_at' => $verified ? now()->subHours(12) : null,
                'rejection_reason' => $payment['rejection_reason'] ?? null,
            ]
        );
    }

    /**
     * @return array<string, Facility>
     */
    private function facilities(): array
    {
        $names = collect($this->guests())
            ->flatMap(fn (array $guest) => collect($guest['reservations'])->pluck('facility'))
            ->unique()
            ->values();

        return Facility::query()
            ->whereIn('name', $names)
            ->get()
            ->keyBy('name')
            ->all();
    }

    private function basePrice(Facility $facility, int $nights): float
    {
        $rate = $facility->rates()
            ->where('type', RateType::Base->value)
            ->where('is_active', true)
            ->first();

        $nightly = $rate ? (float) $rate->amount : 3500.00;

        return round($nightly * $nights, 2);
    }

    private function guestIdTypeFromDemo(?string $demoId): ?string
    {
        if ($demoId === null || $demoId === '') {
            return null;
        }

        if (GuestIdType::tryFrom($demoId) !== null) {
            return $demoId;
        }

        $upper = strtoupper($demoId);

        return match (true) {
            str_starts_with($upper, 'PHILID') => GuestIdType::PhilSys->value,
            str_starts_with($upper, 'PASSPORT') => GuestIdType::Passport->value,
            str_starts_with($upper, 'DL-') => GuestIdType::DriversLicense->value,
            str_starts_with($upper, 'UMID') => GuestIdType::Umid->value,
            str_starts_with($upper, 'PRC') => GuestIdType::Prc->value,
            default => GuestIdType::PhilSys->value,
        };
    }
}
