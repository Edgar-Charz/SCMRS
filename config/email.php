<?php
return [
    // SMTP connection
    // Local (MailHog):  host=localhost  port=1025  user=''  pass=''  encryption=''
    // Production Gmail: host=smtp.gmail.com  port=587  encryption='tls'
    //                   user=your.address@gmail.com  pass=<App Password>
    'host'       => 'localhost',
    'port'       => 1025,
    'username'   => '',
    'password'   => '',
    'encryption' => '',           // '' | 'tls' | 'ssl'

    // Sender identity 
    'from_email' => 'noreply@scmrs.udsm.ac.tz',
    'from_name'  => 'UDSM SCMRS',

    // Base URL used to build full links inside emails 
    // No trailing slash. Change this to your production URL when deploying.
    'app_url'    => 'http://localhost/scmrs',

    // Queue behaviour 
    'max_attempts' => 3,   // give up after this many failures
    'batch_size'   => 10,  // emails sent per shutdown cycle
];
