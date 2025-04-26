<!DOCTYPE html>
<html>
<head>
    <title>Violation Notification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            margin-bottom: 20px;
        }
        .content {
            margin-bottom: 20px;
            white-space: pre-line;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>BPLD Violation Notice</h2>
        </div>
        <div class="content">
            {!! nl2br(e($messageContent)) !!}
        </div>
    </div>
</body>
</html>
