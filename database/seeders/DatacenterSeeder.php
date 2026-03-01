<?php

namespace Database\Seeders;

use App\Models\Datacenter;
use Illuminate\Database\Seeder;

/**
 * Seeds the database with sample datacenter records for development.
 *
 * Creates 18 datacenters across global regions:
 * - North America: NYC, Silicon Valley, Chicago, Dallas, Seattle, Toronto
 * - Europe: Frankfurt, London, Amsterdam, Paris
 * - Asia-Pacific: Singapore, Tokyo, Hong Kong, Sydney, Mumbai
 * - Latin America: São Paulo
 * - Middle East: Dubai
 */
class DatacenterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Only seed if no datacenters exist
        if (Datacenter::count() > 0) {
            return;
        }

        // Create a flagship datacenter with all details (for showcase)
        Datacenter::create([
            'name' => 'NYC Metro Data Center',
            'address_line_1' => '111 8th Avenue',
            'address_line_2' => 'Floor 4',
            'city' => 'New York',
            'state_province' => 'NY',
            'postal_code' => '10011',
            'country' => 'United States',
            'company_name' => 'Metro Colocation Services',
            'primary_contact_name' => 'Robert Chen',
            'primary_contact_email' => 'r.chen@metrocolo.example.com',
            'primary_contact_phone' => '+1-212-555-0100',
            'secondary_contact_name' => 'Maria Santos',
            'secondary_contact_email' => 'm.santos@metrocolo.example.com',
            'secondary_contact_phone' => '+1-212-555-0101',
        ]);

        // Create a west coast datacenter with company but no secondary
        Datacenter::create([
            'name' => 'Silicon Valley Campus DC',
            'address_line_1' => '2500 Technology Drive',
            'address_line_2' => 'Building B',
            'city' => 'San Jose',
            'state_province' => 'CA',
            'postal_code' => '95110',
            'country' => 'United States',
            'company_name' => 'Valley Tech Infrastructure',
            'primary_contact_name' => 'Jennifer Liu',
            'primary_contact_email' => 'j.liu@valleytech.example.com',
            'primary_contact_phone' => '+1-408-555-0200',
        ]);

        // Create a European datacenter
        Datacenter::create([
            'name' => 'Frankfurt Central DC',
            'address_line_1' => 'Hanauer Landstrasse 298',
            'city' => 'Frankfurt',
            'state_province' => 'Hessen',
            'postal_code' => '60314',
            'country' => 'Germany',
            'company_name' => 'EuroHost GmbH',
            'primary_contact_name' => 'Klaus Mueller',
            'primary_contact_email' => 'k.mueller@eurohost.example.de',
            'primary_contact_phone' => '+49-69-555-0300',
            'secondary_contact_name' => 'Anna Schmidt',
            'secondary_contact_email' => 'a.schmidt@eurohost.example.de',
            'secondary_contact_phone' => '+49-69-555-0301',
        ]);

        // Create an Asian datacenter with minimal info
        Datacenter::create([
            'name' => 'Singapore Equinix SG1',
            'address_line_1' => '26A Ayer Rajah Crescent',
            'city' => 'Singapore',
            'state_province' => 'Singapore',
            'postal_code' => '139963',
            'country' => 'Singapore',
            'primary_contact_name' => 'David Tan',
            'primary_contact_email' => 'd.tan@equinix.example.sg',
            'primary_contact_phone' => '+65-6555-0400',
        ]);

        // Chicago datacenter
        Datacenter::create([
            'name' => 'Chicago Digital Realty CH1',
            'address_line_1' => '350 East Cermak Road',
            'city' => 'Chicago',
            'state_province' => 'IL',
            'postal_code' => '60616',
            'country' => 'United States',
            'company_name' => 'Digital Realty Trust',
            'primary_contact_name' => 'Michael Johnson',
            'primary_contact_email' => 'm.johnson@digitalrealty.example.com',
            'primary_contact_phone' => '+1-312-555-0500',
            'secondary_contact_name' => 'Sarah Williams',
            'secondary_contact_email' => 's.williams@digitalrealty.example.com',
            'secondary_contact_phone' => '+1-312-555-0501',
        ]);

        // Dallas datacenter
        Datacenter::create([
            'name' => 'Dallas Infomart DC',
            'address_line_1' => '1950 North Stemmons Freeway',
            'address_line_2' => 'Suite 3000',
            'city' => 'Dallas',
            'state_province' => 'TX',
            'postal_code' => '75207',
            'country' => 'United States',
            'company_name' => 'Infomart Data Centers',
            'primary_contact_name' => 'James Rodriguez',
            'primary_contact_email' => 'j.rodriguez@infomart.example.com',
            'primary_contact_phone' => '+1-214-555-0600',
        ]);

        // Seattle datacenter
        Datacenter::create([
            'name' => 'Seattle Westin Building',
            'address_line_1' => '2001 Sixth Avenue',
            'city' => 'Seattle',
            'state_province' => 'WA',
            'postal_code' => '98121',
            'country' => 'United States',
            'primary_contact_name' => 'Emily Park',
            'primary_contact_email' => 'e.park@westinbuilding.example.com',
            'primary_contact_phone' => '+1-206-555-0700',
        ]);

        // Toronto datacenter
        Datacenter::create([
            'name' => 'Toronto Cologix TOR1',
            'address_line_1' => '151 Front Street West',
            'address_line_2' => 'Lower Level',
            'city' => 'Toronto',
            'state_province' => 'ON',
            'postal_code' => 'M5J 2N1',
            'country' => 'Canada',
            'company_name' => 'Cologix Canada',
            'primary_contact_name' => 'Marc Tremblay',
            'primary_contact_email' => 'm.tremblay@cologix.example.ca',
            'primary_contact_phone' => '+1-416-555-0800',
            'secondary_contact_name' => 'Lisa Chen',
            'secondary_contact_email' => 'l.chen@cologix.example.ca',
            'secondary_contact_phone' => '+1-416-555-0801',
        ]);

        // London datacenter
        Datacenter::create([
            'name' => 'London Telehouse North',
            'address_line_1' => '14 Coriander Avenue',
            'city' => 'London',
            'state_province' => 'Greater London',
            'postal_code' => 'E14 2AA',
            'country' => 'United Kingdom',
            'company_name' => 'Telehouse Europe',
            'primary_contact_name' => 'James Wilson',
            'primary_contact_email' => 'j.wilson@telehouse.example.co.uk',
            'primary_contact_phone' => '+44-20-7555-0900',
            'secondary_contact_name' => 'Emma Thompson',
            'secondary_contact_email' => 'e.thompson@telehouse.example.co.uk',
            'secondary_contact_phone' => '+44-20-7555-0901',
        ]);

        // Amsterdam datacenter
        Datacenter::create([
            'name' => 'Amsterdam Equinix AM3',
            'address_line_1' => 'Kuiperbergweg 13',
            'city' => 'Amsterdam',
            'state_province' => 'North Holland',
            'postal_code' => '1101 AE',
            'country' => 'Netherlands',
            'company_name' => 'Equinix Netherlands',
            'primary_contact_name' => 'Jan de Vries',
            'primary_contact_email' => 'j.devries@equinix.example.nl',
            'primary_contact_phone' => '+31-20-555-1000',
        ]);

        // Paris datacenter
        Datacenter::create([
            'name' => 'Paris Interxion PAR5',
            'address_line_1' => '129 Boulevard Malesherbes',
            'city' => 'Paris',
            'state_province' => 'Île-de-France',
            'postal_code' => '75017',
            'country' => 'France',
            'company_name' => 'Interxion France',
            'primary_contact_name' => 'Pierre Dubois',
            'primary_contact_email' => 'p.dubois@interxion.example.fr',
            'primary_contact_phone' => '+33-1-5555-1100',
            'secondary_contact_name' => 'Marie Laurent',
            'secondary_contact_email' => 'm.laurent@interxion.example.fr',
            'secondary_contact_phone' => '+33-1-5555-1101',
        ]);

        // Tokyo datacenter
        Datacenter::create([
            'name' => 'Tokyo Equinix TY2',
            'address_line_1' => '2-2-2 Shinonome',
            'address_line_2' => 'Koto-ku',
            'city' => 'Tokyo',
            'state_province' => 'Tokyo',
            'postal_code' => '135-0062',
            'country' => 'Japan',
            'company_name' => 'Equinix Japan',
            'primary_contact_name' => 'Takeshi Yamamoto',
            'primary_contact_email' => 't.yamamoto@equinix.example.jp',
            'primary_contact_phone' => '+81-3-5555-1200',
            'secondary_contact_name' => 'Yuki Tanaka',
            'secondary_contact_email' => 'y.tanaka@equinix.example.jp',
            'secondary_contact_phone' => '+81-3-5555-1201',
        ]);

        // Hong Kong datacenter
        Datacenter::create([
            'name' => 'Hong Kong MEGA-i',
            'address_line_1' => '399 Chai Wan Road',
            'city' => 'Hong Kong',
            'state_province' => 'Hong Kong',
            'postal_code' => '000000',
            'country' => 'Hong Kong',
            'company_name' => 'iAdvantage Limited',
            'primary_contact_name' => 'Kevin Wong',
            'primary_contact_email' => 'k.wong@iadvantage.example.hk',
            'primary_contact_phone' => '+852-2555-1300',
        ]);

        // Sydney datacenter
        Datacenter::create([
            'name' => 'Sydney Global Switch SY1',
            'address_line_1' => '400 Harris Street',
            'city' => 'Sydney',
            'state_province' => 'NSW',
            'postal_code' => '2007',
            'country' => 'Australia',
            'company_name' => 'Global Switch Australia',
            'primary_contact_name' => 'Chris Mitchell',
            'primary_contact_email' => 'c.mitchell@globalswitch.example.au',
            'primary_contact_phone' => '+61-2-5555-1400',
            'secondary_contact_name' => 'Rachel Brown',
            'secondary_contact_email' => 'r.brown@globalswitch.example.au',
            'secondary_contact_phone' => '+61-2-5555-1401',
        ]);

        // Mumbai datacenter
        Datacenter::create([
            'name' => 'Mumbai GPX India',
            'address_line_1' => 'Hiranandani Business Park',
            'address_line_2' => 'Powai',
            'city' => 'Mumbai',
            'state_province' => 'Maharashtra',
            'postal_code' => '400076',
            'country' => 'India',
            'company_name' => 'GPX Global Systems',
            'primary_contact_name' => 'Rajesh Sharma',
            'primary_contact_email' => 'r.sharma@gpxglobal.example.in',
            'primary_contact_phone' => '+91-22-5555-1500',
        ]);

        // São Paulo datacenter
        Datacenter::create([
            'name' => 'São Paulo Ascenty SP4',
            'address_line_1' => 'Avenida Paulista, 1754',
            'city' => 'São Paulo',
            'state_province' => 'SP',
            'postal_code' => '01310-200',
            'country' => 'Brazil',
            'company_name' => 'Ascenty Data Centers',
            'primary_contact_name' => 'Carlos Silva',
            'primary_contact_email' => 'c.silva@ascenty.example.br',
            'primary_contact_phone' => '+55-11-5555-1600',
            'secondary_contact_name' => 'Ana Oliveira',
            'secondary_contact_email' => 'a.oliveira@ascenty.example.br',
            'secondary_contact_phone' => '+55-11-5555-1601',
        ]);

        // Dubai datacenter
        Datacenter::create([
            'name' => 'Dubai Khazna DC1',
            'address_line_1' => 'Dubai Silicon Oasis',
            'city' => 'Dubai',
            'state_province' => 'Dubai',
            'postal_code' => '00000',
            'country' => 'United Arab Emirates',
            'company_name' => 'Khazna Data Centers',
            'primary_contact_name' => 'Ahmed Al-Rashid',
            'primary_contact_email' => 'a.alrashid@khazna.example.ae',
            'primary_contact_phone' => '+971-4-555-1700',
        ]);
    }
}
