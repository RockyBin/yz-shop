<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=Edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0,user-scalable=yes">
    <style type="text/css">
        body{
            margin:0;
        }
        .paper {
            margin: 0 auto;
            min-height: 300px;
            height:100%;
            position: relative;
        }
        .paper-background {
            width: 100%;
        }
        .paper-background > img {
            width: 100%;
        }
        .module-list {
            width: 100%;
            height: 100%;
            position: absolute;
            top: 0;
            left: 0;
			z-index: 999;
        }
        .module-item {
            box-sizing: border-box;
        }
        /*昵称模块*/
        .ModuleNickName {
            text-align: left;
            display: flex;
            align-items: center;
        }
        .ModuleNickName .text-container {
            flex: 1;
            white-space: nowrap;
        }
        /*文本模块*/
        .ModuleText {
            text-align: left;
            display: flex;
            align-items: center;
        }
        .ModuleText .text-container {
            flex: 1;
        }
        /*头像模块*/
        .ModuleHead .img-container {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }
        .ModuleHead .img-container .img {
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            /*object-fit: cover;*/
        }
        /*二维码模块*/
        .ModuleQrcode .qrdiv {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }
        .ModuleQrcode .qrdiv .qrimg {
            width: 100%;
            object-fit: contain;
        }
        .ModuleQrcode .qrdiv .logo  {
            width: 30%;
            height: 30%;
            position: absolute;
            top: 50%;
            left: 50%;
        }
        .ModuleQrcode .qrdiv .logo img {
            width: 100%;
            margin-top: -50%;
            margin-left: -50%;
        }
        /*图片模块*/
        .ModuleImage > div {
            position: absolute;
            width: 100%;
            height: 100%;
            background-position: center;
            background-size: cover;
        }
        .ModuleImage > img {
            position: absolute;
            width: 100%;
            height: 100%;
        }
    </style>
</head>
<body>
<div class="paper">
    <div class="paper-background">
        @if($background)
            <img src="{{$background}}"/>
        @endif
    </div>
    <div class="module-list">
        @foreach ($modules as $module)
            <div class="module-item {{$module['module_type']}}" style="{{getModuleStyle($module)}}">
                @if ($module['module_type'] == 'ModuleHead')
                    <div class="img-container">
                        <img class="img" src="{{$memberInfo['headurl']}}">
                    </div>
                @elseif ($module['module_type'] == 'ModuleNickName')
                    <div class="text-container" style="{{getNickNameStyle($module,$fontscale)}}">
                        {{$memberInfo['nickname']}}
                    </div>
                @elseif ($module['module_type'] == 'ModuleText')
                    <div class="text-container" style="{{getTextStyle($module,$fontscale)}}">
                        {{$module['text']}}
                    </div>
                @elseif ($module['module_type'] == 'ModuleImage')
                    <div style="{{getImageStyle($module)}}"></div>
                @elseif ($module['module_type'] == 'ModuleQrcode')
                    <div class="qrdiv">
                        <img class="qrimg" src="{{$module['qrdata']}}">
                        @if ($module['logo'])
                            <div class="logo"><img src="{{$module['logo']}}"></div>
                        @endif
                    </div>
                @else

                @endif
            </div>
        @endforeach
    </div>
</div>
</body>
</html>

@php
    function getModuleStyle($module){
        $css = [
            'position' => $module['position'],
            'width' => $module['width'],
            'height' => $module['height'],
            'top' => $module['top'],
            'left' => $module['left'],
            'z-index' => $module['zIndex']
        ];
        return getCssString($css);
    }

    function getNickNameStyle($module,$fontscale){
        $css = [
            'color' => $module['color'],
            'text-align' => $module['textAlign'] ? $module['textAlign'] : 'left',
            'font-weight' => $module['bold'] ? 'bold':'normal',
            'font-size' => ($module['fontSize'] * $fontscale).'px'
        ];
       return getCssString($css);
    }

    function getTextStyle($module,$fontscale){
        $css = [
            'color' => $module['color'],
            'text-align' => $module['textAlign'] ? $module['textAlign'] : 'left',
            'font-weight' => $module['bold'] ? 'bold':'normal',
            'font-size' => ($module['fontSize'] * $fontscale).'px'
        ];
        return getCssString($css);
    }

    function getImageStyle($module){
        $css = [
            'background-image' => 'url('.$module['src'].')',
            'border-radius' =>  $module['borderRadius']
        ];
        return getCssString($css);
    }

    function getCssString($cssArr){
        $style = '';
        foreach($cssArr as $key => $val){
            $style .= $key.':'.$val.';';
        }
        return $style;
    }
@endphp
