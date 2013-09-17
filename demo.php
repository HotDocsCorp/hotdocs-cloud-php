<!DOCTYPE html>
<html>
  <head>
    <title>HotDocs Cloud Services PHP Client Demo</title>
    <script type="text/javascript" src="http://files.hotdocs.ws/download/easyXDM.min.js"></script>
    <script type="text/javascript" src="http://files.hotdocs.ws/download/hotdocs.js"></script>
  </head>
  <?php
    require 'hotdocs-cloud-1.0.0.php';
    
    $client = new Client('SUBSCRIBER_ID', 'SIGNING_KEY');
    $request = new CreateSessionRequest('Employment Agreement', 'C:\myfilepath\EmploymentAgreement.hdpkg');
    $sessionId = $client->sendRequest($request);
  ?>    
  <body onload="HD$.CreateInterviewFrame('interview', '<?php echo $sessionId ?>');">
    <form id="form1">
      <h1>Employment Agreement Generator</h1>
      <div id="interview" style="width:100%; height:600px; border:1px solid black">
      </div>
    </form>
  </body>

</html>
