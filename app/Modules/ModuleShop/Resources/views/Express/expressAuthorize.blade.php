<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>授权结果</title>
    <style type="text/css">
        body{
            width: 100%;
            height: 100%;
            margin: 0;
            background: #f6fafc;
        }
        .logo-div {
            margin-top: 28px;
            margin-bottom: 56px;
            text-align: center;
        }
        .express-logo {
            width: 180px;
        }
        .result-img-div {
            text-align: center;
            margin-bottom: 20px;
        }
        .result-img {
            width: 115px;
        }
        .result-msg-div {
            font-size: 16px;
            font-weight: bold;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="content">
    <div class="logo-div">
        <img class="express-logo" src="/sysdata/express/express100.png" alt="快递100">
    </div>
    <div class="result-img-div">
        @if($status == 200)
            <img class="result-img" src="/sysdata/express/authorization-success.png" />
        @else
            <img class="result-img" src="/sysdata/express/authorization-fail.png">
        @endif
    </div>
    <div class="result-msg-div">
        {{$msg}}
    </div>
</div>
</body>
</html>
