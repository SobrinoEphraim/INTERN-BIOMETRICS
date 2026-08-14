TRAINEE EVALUATION SYSTEM - SETUP GUIDE (XAMPP)
==================================================

1. I-INSTALL ANG XAMPP
   Download sa https://www.apachefriends.org kung wala ka pa.
   Patakbuhin ang Apache at MySQL sa XAMPP Control Panel.

2. ILAGAY ANG PROJECT FOLDER
   I-copy ang buong "anes-project" folder papunta sa:
      C:\xampp\htdocs\anes-project      (Windows)
      /Applications/XAMPP/htdocs/anes-project   (Mac)

   Pwede mo palitan ang pangalan ng folder base sa gusto mo,
   basta i-adjust yung URL mo sa browser kapag nag-access ka.

3. GUMAWA NG DATABASE
   a. Buksan ang http://localhost/phpmyadmin
   b. Click "Import" tab
   c. I-import ang file na: database/schema.sql
      (gagawa ito automatic ng database na "trainee_eval_system"
       at ng "users" table, plus isang placeholder admin account)

4. I-GENERATE ANG TAMANG PASSWORD HASH NG DEFAULT ADMIN
   Ang password hash na nasa schema.sql ay placeholder lang
   (hindi valid). Kailangan mo munang gumawa ng tunay na hash:

   a. Buksan sa browser: http://localhost/anes-project/generate_admin_hash.php
   b. Kokopyahin mo yung lalabas na hash (mahabang text na
      nagsisimula sa $2y$...)
   c. Sa phpMyAdmin, buksan ang "users" table, i-edit yung row
      ng admin@nkti.gov.ph, at i-paste yung kinopya mong hash
      sa column na "password_hash"
   d. I-delete na ang generate_admin_hash.php sa server pagkatapos
      (para hindi na ma-access ulit).

5. I-TEST ANG LOGIN
   Buksan: http://localhost/anes-project/login.php

   Default admin account:
      Email: admin@nkti.gov.ph
      Password / Access Code: NktiAnes2026

   Sa unang login, itatanong sayo ang bagong password
   (reset_password.php) bago ka ma-redirect sa admin dashboard.

6. PAANO MAGDAGDAG NG BAGONG USER (TRAINEE / CONSULTANT / RATER)
   Sa loob ng Admin Dashboard, i-click ang "Add New User".
   Ang bawat bagong account ay awtomatikong gagamit ng parehong
   default access code (NktiAnes2026) bilang panimulang password,
   tapos ipapatanong din sa kanila na gumawa ng sarili nilang
   password sa first login nila — kagaya ng ginawa mong flow.

FILE STRUCTURE
--------------
anes-project/
  config/
    db_connect.php     <- database connection settings (i-edit kung
                           iba ang MySQL username/password mo)
    auth_check.php      <- helper para protektahan ang mga pages
  database/
    schema.sql           <- i-import ito sa phpMyAdmin
  admin/
    dashboard.php         <- admin home
    create_user.php        <- form para magdagdag ng user
    manage_users.php        <- listahan, enable/disable, delete
  user/
    dashboard.php           <- dashboard ng regular users (i-expand
                                mo pa ito ng rating forms, etc.)
  images/
    anesthlogo.png            <- logo mo
  login.php                    <- login page (naka-connect na sa DB)
  logout.php
  reset_password.php            <- unang password setup
  generate_admin_hash.php        <- pang-isang gamit lang, tapos
                                     i-delete mo na

NEXT STEPS NA PWEDE MONG IDAGDAG
---------------------------------
- Rating/evaluation forms table (para sa "Rate peers", "Evaluate
  trainees", "Rate consultants" na nakita sa dashboard mockup mo)
- Forgot password flow (forgot_password.php - kailangan mo pa
  gawin, wala pa dito)
- Audit log / activity tracking
- Email notifications (kailangan ng mail server config, hal.
  PHPMailer + Gmail SMTP)
