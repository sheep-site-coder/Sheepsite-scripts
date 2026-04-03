<?php
// -------------------------------------------------------
// buildings.php — Central building configuration
//
// THIS IS THE ONLY FILE TO EDIT when adding a new building.
// All other PHP scripts load this file automatically.
//
// Each entry requires:
//   publicFolderId  — Google Drive folder ID for BuildingName/WebSite/Public
//   privateFolderId — Google Drive folder ID for BuildingName/WebSite/Private
//   webAppURL       — Apps Script Web App deployment URL for the building's Google Sheet
//   state           — Two-letter state code for FAQ layer (e.g. 'FL'); defaults to 'FL' if omitted
//   community       — Master community name for FAQ layer (e.g. 'CVE'); omit if none
// -------------------------------------------------------

return [
  'QGscratch' => [
    'state'           => 'FL',
    'publicFolderId'  => '1Vgnk3XTKta33deoOWUfOp9Z666jHpM1c',  // QGscratch/WebSite/Public
    'privateFolderId' => '1cnHRemgPPNWbY9QlyrsHXq6Mzdu09tSu',  // QGscratch/WebSite/Private
    'webAppURL'       => 'https://script.google.com/macros/s/DEPLOYMENT_ID_QGSCRATCH/exec',
  ],
  'SampleSite' => [
    'state'           => 'FL',
    'community'       => 'CVE',
    'publicFolderId'  => '1etbQzwJ30sLbSO7rNouEcAOQ_gUS1lHN',  // SampleSite/WebSite/Public
    'privateFolderId' => '1DVONmgPHxKLETKHRv-sJxnBmCx0B50Tb',  // SampleSite/WebSite/Private
    'webAppURL'       => 'https://script.google.com/macros/s/AKfycbyEqHwoX1ju5s--vNfy7U50BJH0FYLHQRN5UOqUAqotcHFlS2hBF51-K9I7QScgGyUw-w/exec',
    // Site template fields
    'displayName'     => 'SampleSite Condo',
    'headerImageUrl'  => 'https://sheepsite.com/Scripts/assets/SampleSite-header.jpg',
    'calendarUrl'     => '',   // Google Calendar URL for this building
    'facebookUrl'     => '',   // Facebook group/page URL for this building
    'propertyMgmt'    => [
      'name'  => 'Seacrest',
      'url'   => 'https://home.seacrestservices.com/login',
      'phone' => '1-888-828-6464',
    ],
  ],
  'LyndhurstH' => [
    'state'           => 'FL',
    'community'       => 'CVE',
    'publicFolderId'  => '1nJyAbZ8vCAMSKKheU-39DDZB2hXvC97g',  // LyndhurstH/WebSite/Public
    'privateFolderId' => '11WXnAU2P-ShZPtj9p5PG0bFR7ehDXUSS',  // LyndhurstH/WebSite/Private
    'webAppURL'       => 'https://script.google.com/macros/s/AKfycbwsLZ710fdJgJP_YgJ2yXa2XKwzwYzVUj-c1xEpyefHoYeG8bOwJ407ByWCGGOKzmns/exec',
  ],
  'LyndhurstI' => [
    'state'           => 'FL',
    'community'       => 'CVE',
    'publicFolderId'  => '1zL9-FMMKn1uufMZWUw24lywflCVL44Rc',  // LyndhurstI/WebSite/Public
    'privateFolderId' => '1xNEXK2qcGoISKaNoChbTDn2FOWnUmSFP',  // LyndhurstI/WebSite/Private
    'webAppURL'       => 'https://script.google.com/macros/s/AKfycbxgKbwsbX8PkPcf5mJRZ5ZplKg8qRDHXyBCwSOHWIcCXTCZCwS87VtyWtxnCv0_vCHL/exec',
  ],
  // add more buildings here...
];
