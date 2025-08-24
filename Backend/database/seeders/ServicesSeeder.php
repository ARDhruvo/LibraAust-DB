<?php

// database/seeders/PageSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServicesSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement("
        INSERT INTO services (slug, description, created_at, updated_at)
        VALUES 
            (
                'kfr',
                'Our Mission
    The KFR Library at Ahsanullah University of Science and Technology serves as the intellectual heart of our campus, providing comprehensive resources and services to support the academic and research endeavors of our students, faculty, and staff.

    Named after the university\'s founder, KFR Library is committed to fostering a culture of learning, innovation, and knowledge sharing through state-of-the-art facilities and expert library services.

    Library Facilities
    - Over 15,000 books covering all engineering disciplines and general subjects
    - Access to IEEE, ACM, and other international digital libraries
    - High-speed WiFi
    - Silent reading rooms, group study areas, and discussion zones

    Library Hours
    - Sunday - Thursday: 8:00 AM - 8:00 PM
    - Friday: 8:00 AM - 12:00 PM
    - Saturday: 9:00 AM - 5:00 PM
    * Extended hours during exam periods

    Contact
    +880-2-8870422
    library@aust.edu
    2nd Floor, Academic Building, AUST',
                NOW(),
                NOW()
            ),
            (
                'rules',
    '
    Membership Eligibility
    * All AUST students, faculty, and staff are eligible
    * Valid university ID card required
    * Membership activated on enrollment/employment
    * Lost ID cards must be reported immediately

    Borrowing Policies
    Students: 3 books / 14 days / 1 renewal
    Faculty: 10 books / 30 days / 2 renewals
    Research Students: 5 books / 21 days / 1 renewal
    * Reference books and journals cannot be borrowed
    * Reservations available online or at the desk

    Fine Structure
    - Late return: 5 BDT per book per day
    - Lost book: replacement cost + 100 BDT
    - Damaged book: repair or replacement
    - Lost ID card: 50 BDT replacement fee

    Code of Conduct
    - Maintain silence in reading areas
    - No food/drinks, no smoking
    - Phones on silent
    - Handle books with care
    - No unauthorized photography
    - No sleeping in the library

    Digital Resources Policy
    - Academic/research purposes only
    - No credential sharing
    - No bulk downloads
    - Respect copyright

    Need Clarification?
    Phone: +880-2-8870422
    Email: library@aust.edu
    Desk Hours: 8:00 AM - 6:00 PM',
                NOW(),
                NOW()
            )
    ");
    }
}

