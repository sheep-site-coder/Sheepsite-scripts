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
// -------------------------------------------------------

return [
  'QGscratch' => [
    'publicFolderId'  => '1Vgnk3XTKta33deoOWUfOp9Z666jHpM1c',  // QGscratch/WebSite/Public
    'privateFolderId' => '1cnHRemgPPNWbY9QlyrsHXq6Mzdu09tSu',  // QGscratch/WebSite/Private
    'webAppURL'       => 'https://script.google.com/macros/s/DEPLOYMENT_ID_QGSCRATCH/exec',
  ],
  'SampleSite' => [
    'publicFolderId'  => '1etbQzwJ30sLbSO7rNouEcAOQ_gUS1lHN',  // SampleSite/WebSite/Public
    'privateFolderId' => '1DVONmgPHxKLETKHRv-sJxnBmCx0B50Tb',  // SampleSite/WebSite/Private
    'webAppURL'       => 'https://script.google.com/macros/s/AKfycbyEqHwoX1ju5s--vNfy7U50BJH0FYLHQRN5UOqUAqotcHFlS2hBF51-K9I7QScgGyUw-w/exec',
  ],
  'LyndhurstH' => [
    'publicFolderId'  => '1nJyAbZ8vCAMSKKheU-39DDZB2hXvC97g',  // LyndhurstH/WebSite/Public
    'privateFolderId' => '11WXnAU2P-ShZPtj9p5PG0bFR7ehDXUSS',  // LyndhurstH/WebSite/Private
    'webAppURL'       => 'https://script.google.com/macros/s/AKfycbwsLZ710fdJgJP_YgJ2yXa2XKwzwYzVUj-c1xEpyefHoYeG8bOwJ407ByWCGGOKzmns/exec',
  ],
  'LyndhurstI' => [
    'publicFolderId'  => '1zL9-FMMKn1uufMZWUw24lywflCVL44Rc',  // LyndhurstI/WebSite/Public
    'privateFolderId' => '1xNEXK2qcGoISKaNoChbTDn2FOWnUmSFP',  // LyndhurstI/WebSite/Private
    'webAppURL'       => 'https://script.google.com/macros/s/DEPLOYMENT_ID_LYNDHURSTI/exec',
  ],
  // add more buildings here...
];
