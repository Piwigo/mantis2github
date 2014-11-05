<?php

$conf = array(
  // your mantis database
  'db' => array(
    'host' => 'localhost',
    'name' => 'bugs',
    'user' => 'root',
    'pwd' => '',
    'encode' => 'ISO-8859-1', // encoding of text fields
    ),

  // your github repository
  'repo' => array(
    'user' => 'USERNAME OR ORGANISATION',
    'name' => 'PROJECT NAME',
    ),

  // define user mappings
  'users' => array(
    // users with a password could be the creator of an issue or comment
    'YOUR MANTIS LOGIN' => array(
      'login' => 'YOUR GITHUB LOGIN',
      'pwd' => 'YOUR GITHUB PASSWORD',
      ),

    // users without password could be setted as issue assignee
    'ANOTHER MANTIS LOGIN' => array(
      'login' => 'ANOTHER GITHUB LOGIN',
      ),
    ),
  
  // the default user MUST have a password defined
  'default_user' => 'YOUR MANTIS LOGIN',
  
  // set the default user as assignee for every assigned issue where the Github user can't be found
  'assign_to_default_user' => true,
  
  // which mantis projects to import ?
  // table: mantis_project_table
  'projects' => array(1),
  
  // give a simple (chars only, no spaces) name to your custom fields
  // table: mantis_custom_field_table
  'fields' => array(
    //2 => 'php',
    //3 => 'webserver',
   ),
   
   // import resolved and closed tickets ?
  'import_resolved' => false,
  
   // import private tickets ?
  'import_private' => false,
  
   // user ids for which comments won't be imported (ex: SVN automated user)
  'bugnote_ban_users' => array(),
  
   // add a link to your mantis at the end of the issue ?
  'backlink' => array(
    'enable' => true,
    'title' => 'Mantis Bugtracker #%d',
    'url' => 'https://www.mantisbt.org/bugs/view.php?id=%d',
    ),
  );
