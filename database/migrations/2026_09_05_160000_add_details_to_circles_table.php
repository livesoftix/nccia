<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Zone;
use App\Models\Circle;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('circles', function (Blueprint $table) {
            if (!Schema::hasColumn('circles', 'address')) {
                $table->string('address', 500)->nullable()->after('zone_id');
            }
            if (!Schema::hasColumn('circles', 'phone')) {
                $table->string('phone', 100)->nullable()->after('address');
            }
            if (!Schema::hasColumn('circles', 'jurisdiction')) {
                $table->text('jurisdiction')->nullable()->after('phone');
            }
        });

        // Seed / Update Official NCCIA Zones
        $zoneFederal = Zone::firstOrCreate(['code' => 'FCZ'], ['name' => 'Federal Capital Zone (HQ)']);
        $zonePunjab = Zone::firstOrCreate(['code' => 'PZ'], ['name' => 'Punjab Zone']);
        $zoneKpk = Zone::firstOrCreate(['code' => 'KPZ'], ['name' => 'Khyber Pakhtunkhwa Zone']);
        $zoneBalochistan = Zone::firstOrCreate(['code' => 'BZ'], ['name' => 'Balochistan Zone']);
        $zoneGb = Zone::firstOrCreate(['code' => 'GBZ'], ['name' => 'Gilgit-Baltistan Zone']);
        
        $zoneSindh = Zone::where('code', 'SZ')->first();
        if ($zoneSindh) {
            $zoneSindh->update(['name' => 'Sindh Zone']);
        } else {
            $zoneSindh = Zone::create(['name' => 'Sindh Zone', 'code' => 'SZ']);
        }

        $officialCircles = [
            // Punjab
            [
                'code' => 'LHR',
                'name' => 'NCCIA Lahore (Central Directorate)',
                'zone_id' => $zonePunjab->id,
                'address' => 'House No. B-8, G Block Main Boulevard Gulberg-II, Lahore',
                'phone' => '042-99263451',
                'jurisdiction' => 'Lahore, Kasur, Sheikhupura, Nankana Sahib, Sahiwal, Okara, Pakpattan',
            ],
            [
                'code' => 'RWP',
                'name' => 'NCCIA Rawalpindi',
                'zone_id' => $zonePunjab->id,
                'address' => 'Regional Directorate NCCIA, Rawalpindi',
                'phone' => '051-9290001',
                'jurisdiction' => 'Rawalpindi, Attock, Chakwal, Jhelum, Murree, Talagang',
            ],
            [
                'code' => 'MUX',
                'name' => 'NCCIA Multan',
                'zone_id' => $zonePunjab->id,
                'address' => 'Regional Directorate NCCIA, Multan',
                'phone' => '061-9201001',
                'jurisdiction' => 'Multan, Khanewal, Lodhran, Vehari, Bahawalpur, Bahawalnagar, Rahim Yar Khan, Dera Ghazi Khan, Layyah, Muzaffargarh, Rajanpur, Taunsa, Kot Addu',
            ],
            [
                'code' => 'GRW',
                'name' => 'NCCIA Gujranwala',
                'zone_id' => $zonePunjab->id,
                'address' => 'Regional Directorate NCCIA, Gujranwala',
                'phone' => '055-9200001',
                'jurisdiction' => 'Gujranwala, Gujrat, Sialkot, Mandi Bahauddin, Narowal, Hafizabad, Wazirabad',
            ],
            [
                'code' => 'FSD',
                'name' => 'NCCIA Faisalabad',
                'zone_id' => $zonePunjab->id,
                'address' => 'Regional Directorate NCCIA, Faisalabad',
                'phone' => '041-9200001',
                'jurisdiction' => 'Faisalabad, Toba Tek Singh, Jhang, Chiniot, Sargodha, Khushab, Mianwali, Bhakkar',
            ],

            // Khyber Pakhtunkhwa (KPK)
            [
                'code' => 'PEW',
                'name' => 'NCCIA Peshawar',
                'zone_id' => $zoneKpk->id,
                'address' => 'Regional Directorate NCCIA, Peshawar',
                'phone' => '091-9210001',
                'jurisdiction' => 'Central, Corporate & Northwestern KPK (Peshawar, Charsadda, Nowshera, Mardan, Swabi, Malakand, Swat, Dir Lower, Dir Upper, Chitral Lower, Chitral Upper, Bajaur, Mohmand, Khyber, Buner, Shangla)',
            ],
            [
                'code' => 'ATD',
                'name' => 'NCCIA Abbottabad',
                'zone_id' => $zoneKpk->id,
                'address' => 'Regional Directorate NCCIA, Hazara Division, Abbottabad',
                'phone' => '0992-9310001',
                'jurisdiction' => 'Abbottabad, Haripur, Mansehra, Batagram, Torghar, Kohistan Upper, Lower Kohistan, Kolai Palas',
            ],
            [
                'code' => 'DIK',
                'name' => 'NCCIA Dera Ismail Khan',
                'zone_id' => $zoneKpk->id,
                'address' => 'Regional Directorate NCCIA, Dera Ismail Khan',
                'phone' => '0966-9280001',
                'jurisdiction' => 'D.I. Khan, Bannu, Lakki Marwat, Tank, Kohat, Karak, Hangu, Kurram, Orakzai, North Waziristan, South Waziristan',
            ],

            // Balochistan
            [
                'code' => 'UET',
                'name' => 'NCCIA Quetta',
                'zone_id' => $zoneBalochistan->id,
                'address' => 'FIA Compound on Shabo Road, Quetta',
                'phone' => '081-9201001',
                'jurisdiction' => 'Quetta, Chaman, Pishin, Zhob, Loralai, Sibi, Nasirabad, Qila Abdullah, Qila Saifullah, Musakhel, Barkhan, Kohlu, Dera Bugti, Ziarat, Harnai, Jaffarabad, Usta Muhammad, Sohbatpur, Jhal Magsi',
            ],
            [
                'code' => 'GWD',
                'name' => 'NCCIA Gwadar',
                'zone_id' => $zoneBalochistan->id,
                'address' => 'New Town Phase-1, Gwadar',
                'phone' => '0864-9200001',
                'jurisdiction' => 'Gwadar, Kech (Turbat), Khuzdar, Kalat, Panjgur, Lasbela, Hub, Awaran, Surab, Washuk, Chagai',
            ],

            // Gilgit-Baltistan
            [
                'code' => 'GLT',
                'name' => 'NCCIA Gilgit-Baltistan',
                'zone_id' => $zoneGb->id,
                'address' => 'Near GDA Office, River Road, Chinarbagh, Gilgit',
                'phone' => '+92 5811 960707',
                'jurisdiction' => 'Gilgit, Hunza, Skardu, Diamir, Astore, Ghizer, Baltistan, Shigar, Nagar, Ghanche, Gupis–Yasin',
            ],

            // Islamabad Headquarters
            [
                'code' => 'ISB',
                'name' => 'NCCIA Islamabad (Headquarters)',
                'zone_id' => $zoneFederal->id,
                'address' => 'NCCIA Headquarters, Islamabad',
                'phone' => '051-9106384',
                'jurisdiction' => 'Islamabad Capital Territory',
            ],

            // Sindh
            [
                'code' => 'KHI',
                'name' => 'NCCIA Karachi',
                'zone_id' => $zoneSindh->id,
                'address' => 'Regional Directorate NCCIA, Karachi',
                'phone' => '021-99201001',
                'jurisdiction' => 'Karachi (South, East, West, Central, Malir, Korangi, Keamari), Hyderabad, Thatta, Sujawal, Badin, Jamshoro, Matiari, Tando Allahyar, Tando Muhammad Khan, Mirpur Khas, Umerkot, Tharparkar, Sukkur, Larkana, Ghotki, Khairpur, Naushahro Feroze, Shaheed Benazirabad, Jacobabad, Kashmore, Shikarpur, Kambar Shahdadkot',
            ],
            [
                'code' => 'SKR',
                'name' => 'NCCIA Sukkur',
                'zone_id' => $zoneSindh->id,
                'address' => 'Regional Directorate NCCIA, Sukkur',
                'phone' => '071-9310001',
                'jurisdiction' => 'Sukkur, Larkana, Khairpur, Ghotki, Jacobabad, Kashmore, Shikarpur, Naushahro Feroze, Kambar Shahdadkot',
            ],
        ];

        foreach ($officialCircles as $cData) {
            $circle = Circle::where('code', $cData['code'])->first();
            if ($circle) {
                $circle->update($cData);
            } else {
                Circle::create($cData);
            }
        }
    }

    public function down(): void
    {
        Schema::table('circles', function (Blueprint $table) {
            if (Schema::hasColumn('circles', 'jurisdiction')) {
                $table->dropColumn('jurisdiction');
            }
            if (Schema::hasColumn('circles', 'phone')) {
                $table->dropColumn('phone');
            }
            if (Schema::hasColumn('circles', 'address')) {
                $table->dropColumn('address');
            }
        });
    }
};
