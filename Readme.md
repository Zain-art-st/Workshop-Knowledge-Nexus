
TLDR; kalau tak jadi tanya

Needed software:

  1. XAMPP 

  2. Git   

  3. A Gmail account (ONLY needed if you want OTP emails to actually
     send.)

----------------------------------------------------------------
  Step 1 —  PLACE THE PROJECT IN THE RIGHT FOLDER (no you cant run this on visual studio sweetie :) )
----------------------------------------------------------------

Place the entire project folder inside XAMPP's htdocs folder.
Folder name example: C:\xampp\htdocs\ScholarSpace\ (Navigate to your own xampp of just search htdocs)
                               
URL depends on nama folder so it'll be like: http://localhost/ScholarSpace/


----------------------------------------------------------------
Step 2 - Start XAMPP
----------------------------------------------------------------


  1. Open XAMPP Control Panel
  2. Click START on Apache
  3. Click START on MySQL

ha done tu je.

Step 3 - Download PHPEmailer

Go to: https://github.com/PHPMailer/PHPMailer 


1. Click the green "Code" button → "Download ZIP"
2. Extract and then letak in one folder as our project


----------------------------------------------------------------
Step 4 - Database
----------------------------------------------------------------


 Make sure XAMPP dah running. Then go to http://localhost/phpmyadmin

Click new and create a new database name scholarspace or whatever and then create.

Click dekat scholarspace on the left hand side dekat panel tu and then click SQL dekat tab atas

Open the file setup.sql from the project folder lepastu copy ALL the contents, paste into the SQL tab then click "Go"

Kalau bnyak hijau, betul la tu


----------------------------------------------------------------
Step 5 - Admin password
----------------------------------------------------------------


Make sure XAMPP tengah running lepastu go to http://localhost/ScholarSpace/admin_reset.php

if there's a big green success messages, it has succeded (mcm mna nk eja success?). After that make sure to delete admin_reset.php

  Admin login credentials:
    Username : admin
    Password : Admin@1234

----------------------------------------------------------------
Step 6 - OTP (Buat la, lagi senang nampak)
----------------------------------------------------------------
Alternative (if you just want to test without real email):
    
	
1. Go to phpMyAdmin → scholarspace → otp_codes table
2. After registering, you can see the OTP code directly in the table
3. Copy it and paste it into the verify page and you can test the full flow without setting up Gmail


if you want email:
Use any of your preferred Google account then pergi ke myaccount.google.com
Pergi security, make sure 2 step verification is enables.
Search app password lepastu create ScholarSpace and copy the password google generates.

Then open mailer.php and fill in:
  1. define('MAIL_FROM',     'here (there's probably my email sbb I yg upload :p.');
  2. define('MAIL_PASSWORD', 'your random 16 char password');

  3. Save the file. 

----------------------------------------------------------------
Step 7 - Open the website
----------------------------------------------------------------
  Go to:   http://localhost/"Nama Folder"/


----------------------------------------------------------------
EXPLANATION ON THE FILE!!!!!:
----------------------------------------------------------------
  index.php         — Entry point, redirects to login or dashboard
  login.php         — Login page (username OR email accepted)
  register.php      — Registration (Step 1: account, Step 2: profile)
  verify_otp.php    — OTP verification page after registration
  dashboard.php     — Main feed after login
  logout.php        — Clears session and redirects to login
  db.php            — Database connection (edit if your MySQL
                       username/password is not root/"")
  mailer.php        — Email config (fill in Gmail credentials here)
  styles.css        — All styling for every page
  setup.sql         — Database schema + seed data (run once)
  admin_reset.php   — Sets admin password (run once then delete)
  PHPMailer\        — Email library (download separately, see Step 3)
  uploads\profiles\ — Profile pictures uploaded by users


----------------------------------------------------------------
Common error:
----------------------------------------------------------------

Connection failed: Unknown database 'scholarspace'"
     You skipped Step 4. Create the database in phpMyAdmin first.

Connection failed: Access denied"
      Open db.php and check the $user and $pass variables.
      Default XAMPP is: $user = "root"  $pass = ""
      If you set a MySQL password, put it in $pass.

Profile photo not uploading
      Make sure the folder  uploads/profiles/  exists inside your
      project folder. Create it manually if it's missing.
      Right-click → New Folder → name it "profiles" inside "uploads".
Call to undefined function" or PHPMailer errors
      PHPMailer folder is missing or in the wrong place. See Step 3.

White screen / blank page
      Enable PHP error display for debugging. Open php.ini in XAMPP,
      find "display_errors = Off" and change to "display_errors = On",
      then restart Apache.

