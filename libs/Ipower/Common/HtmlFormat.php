<?php

namespace Ipower\Common;
class HtmlFormat
{
    public static function format($buffer)
    {
        $buffer = preg_replace(array("/\s+</", "/>\s+/"), array("<", ">"), $buffer);
        preg_match_all('@</?([^\s<>]+)[^<>]*>@i', $buffer, $matchs, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        //print_r($matchs);
        $position = 0;
        $deep = 0;
        //inline类型的标签，如果判断这些标签中没有嵌套其它的标签，结束标签不进行缩进和换行
        $inlineTags = array("title" => 1, "span" => 1, "label" => 1, "a" => 1, "li" => 1, "p" => 1, "input" => 1,"i" => 1,"b" => 1,"strong" => 1,"textarea" => 1);
        //强制不换行的inline类型标签,这些标签一些用户会用来设置不同的字段颜色等，如果换行有时看上去会多了个空格之类的
        $inlineTags2 = array("title" => 1, "span" => 1,"b" => 1,"strong" => 1);
        $inScriptBlock = false;
        for ($i = 0; $i < count($matchs); $i++) {
            $tag = strtolower($matchs[$i][0][0]);
            $tagName = $matchs[$i][1][0];
            $lastTagName = $matchs[$i - 1][1][0];
            $isValidTag = preg_match('/^[a-z]+$/i', $tagName) || substr($tag, 0, 2) == "<!";
            $pos = $matchs[$i][0][1];
            $isTagClose = substr($tag, 0, 2) == "</";
            $isEndIf = substr($tag, 0, 6) == "<![end";

            //脚本块结束
            if ($inScriptBlock && $isTagClose && $tag == '</script>') {
                $inScriptBlock = false;
            }

            //过滤一些无效的匹配，有几个情况
            //1. 如脚本块内可能会有 ' document.write(<scr"+"ipt async type='text/javascript' src='" + countersrc + "'><img src="xxx">)' 这样的代码，要过滤
            //2. 代码内可能有 var a = b < 3 && c > 5 这种情况
            if ($inScriptBlock || !$isValidTag) {
                //echo "skip $tag \r\n";
                continue;
            }

            //echo "$tag $pos $tagName $inScriptBlock \r\n";

            if (!$isTagClose && !$isEndIf) $deep++;
            if ($tagName == 'html' || substr($tag, 0, 9) == '<!doctype') $deep--;

            //echo "$i $tag $tagName = ".$deep."\r\n";

            if ($deep > 0) {
                if ($inlineTags[$tagName] && $isTagClose && $lastTagName == $tagName) $str_to_insert = ""; //inline的结束标签，这里判断的是inline类型的标签中没有嵌套其它标签的情况
                elseif ($inScriptBlock || $tag == '</script>') $str_to_insert = ""; //脚本块内匹配的标签不处理
				elseif ($inlineTags2[$tagName]) $str_to_insert = "";
                else $str_to_insert = "\n" . str_repeat("  ", $deep);
            } else $str_to_insert = "\n";
            if ($str_to_insert) $buffer = substr_replace($buffer, $str_to_insert, $pos + $position, 0);

            //判断脚本块开始
            if (substr($tag, 0, 7) == "<script") $inScriptBlock = true;

            //判断标签结束
            if ($isTagClose && strtolower($tag) != "</input>") $deep--; //结束, <input> 是自结束标签，所以这里不能重复减
            elseif (preg_match('/^<(link|meta|img|input|hr)/', $tag)) $deep--;
            elseif (preg_match('/\-\->$/', $tag)) $deep--;

            if ($deep < 0) $deep = 0;
            //累计总共插入的字符串的长度
            $position += strlen($str_to_insert);
        }
        return trim($buffer);
    }
}
