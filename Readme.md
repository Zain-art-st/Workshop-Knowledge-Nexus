

TLDR; kalau tak jadi tanya



Needed software:



&#x20; 1. XAMPP 



&#x20; 2. Git   



&#x20; 3. A Gmail account (ONLY needed if you want OTP emails to actually

&#x20;    send.)





&#x20; Step 1 —  PLACE THE PROJECT IN THE RIGHT FOLDER (no you cant run this on visual studio sweetie :) )



Place the entire project folder inside XAMPP's htdocs folder.

Folder name example: C:\\xampp\\htdocs\\ScholarSpace\\ (Navigate to your own xampp of just search htdocs)

&#x20;                              

URL depends on nama folder so it'll be like: http://localhost/ScholarSpace/





Step 2 - Start XAMPP





&#x20; 1. Open XAMPP Control Panel

&#x20; 2. Click START on Apache

&#x20; 3. Click START on MySQL



ha done tu je.



Step 3 - Download PHPEmailer



Go to: https://github.com/PHPMailer/PHPMailer 





1\. Click the green "Code" button → "Download ZIP"

2\. Extract and then letak in one folder as our project





Step 4 - Database





&#x20;Make sure XAMPP dah running. Then go to http://localhost/phpmyadmin



Click new and create a new database name scholarspace or whatever and then create.



Click dekat scholarspace on the left hand side dekat panel tu and then click SQL dekat tab atas



Open the file setup.sql from the project folder lepastu copy ALL the contents, paste into the SQL tab then click "Go"



Kalau bnyak hijau, betul la tu





Step 5 - Admin password





Make sure XAMPP tengah running lepastu go to http://localhost/ScholarSpace/admin\_reset.php



if there's a big green success messages, it has succeded (mcm mna nk eja success?). After that make sure to delete admin\_reset.php



&#x20; Admin login credentials:

&#x20;   Username : admin

&#x20;   Password : Admin@1234





Step 6 - OTP (Buat la, lagi senang nampak)





&#x20; Alternative (if you just want to test without real email):

* &#x20;   Go to phpMyAdmin → scholarspace → otp\_codes table
* &#x20;   After registering, you can see the OTP code directly in the table
* &#x20;   Copy it and paste it into the verify page
* &#x20;  This lets you test the full flow without setting up Gmail





Kalau nak email:

1. Use any of your preferred Google account then pergi ke myaccount.google.com
2. Pergi security, make sure 2 step verification is enables.
3. Search app password lepastu create ScholarSpace and copy the password google generates.



Then open mailer.php and fill in:

&#x20;     define('MAIL\_FROM',     'here (there's probably my email sbb I yg upload :p.');

&#x20;     define('MAIL\_PASSWORD', 'your random 16 char password');



&#x20;   Save the file. 



\----------------------------------------------------------------

&#x20; STEP 7 — OPEN THE WEBSITE

\----------------------------------------------------------------



&#x20; Go to:   http://localhost/ScholarSpace/



&#x20; You should see the login page with the sunset background.



&#x20; Test accounts you can create:

&#x20;   - Register with a matric number starting with D03 + year

&#x20;   - Year 23 or above (e.g. D032312345)  → registered as Student

&#x20;   - Year 22 or below (e.g. D032212345)  → registered as Graduate

&#x20;                                            (more fields will appear)



&#x20; Admin account (created by admin\_reset.php):

&#x20;   Username : admin

&#x20;   Password : Admin@1234







\------------------------------------------------------------------------------------------

EXPLANATION ON THE FILE!!!!!:

&#x20; index.php         — Entry point, redirects to login or dashboard

&#x20; login.php         — Login page (username OR email accepted)

&#x20; register.php      — Registration (Step 1: account, Step 2: profile)

&#x20; verify\_otp.php    — OTP verification page after registration

&#x20; dashboard.php     — Main feed after login

&#x20; logout.php        — Clears session and redirects to login

&#x20; db.php            — Database connection (edit if your MySQL

&#x20;                      username/password is not root/"")

&#x20; mailer.php        — Email config (fill in Gmail credentials here)

&#x20; styles.css        — All styling for every page

&#x20; setup.sql         — Database schema + seed data (run once)

&#x20; admin\_reset.php   — Sets admin password (run once then delete)

&#x20; PHPMailer\\        — Email library (download separately, see Step 3)

&#x20; uploads\\profiles\\ — Profile pictures uploaded by users

\-------------------------------------------------------------------------------------------



**Error that I dah hadap:**



**Connection failed: Unknown database 'scholarspace'"**

&#x20;    **You skipped Step 4. Create the database in phpMyAdmin first.**



**Connection failed: Access denied"**

&#x20;     **Open db.php and check the $user and $pass variables.**

&#x20;     **Default XAMPP is: $user = "root"  $pass = ""**

&#x20;     **If you set a MySQL password, put it in $pass.**



**Profile photo not uploading**

&#x20;     **Make sure the folder  uploads/profiles/  exists inside your**

&#x20;     **project folder. Create it manually if it's missing.**

&#x20;     **Right-click → New Folder → name it "profiles" inside "uploads".**

**Call to undefined function" or PHPMailer errors**

&#x20;     **PHPMailer folder is missing or in the wrong place. See Step 3.**



**White screen / blank page**

&#x20;     **Enable PHP error display for debugging. Open php.ini in XAMPP,**

&#x20;     **find "display\_errors = Off" and change to "display\_errors = On",**

&#x20;     **then restart Apache.**





