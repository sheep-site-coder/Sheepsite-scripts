<?php
// -------------------------------------------------------
// buildings.php — Central building configuration
//
// THIS IS THE ONLY FILE TO EDIT when adding a new building.
// All other PHP scripts load this file automatically.
//
// Each entry requires:
//   state     — Two-letter state code for FAQ layer (e.g. 'FL'); defaults to 'FL' if omitted
//   community — Master community name for FAQ layer (e.g. 'CVE'); omit if none
// -------------------------------------------------------

return [
  'QGscratch' => [
    'state' => 'FL',
  ],
  'SampleSite' => [
    'state'     => 'FL',
    'community' => 'CVE',
  ],
  'LyndhurstH' => [
    'state'     => 'FL',
    'community' => 'CVE',
  ],
  'LyndhurstI' => [
    'state'     => 'FL',
    'community' => 'CVE',
  ],
  // add more buildings here...
];
