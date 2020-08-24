<?php
namespace App\Modules\ModuleShop\Libs\TemplateMobi;
use App\Modules\ModuleShop\Libs\Model\TemplateMobiModel;
use App\Modules\ModuleShop\Libs\Model\PageMobiModel;
use App\Modules\ModuleShop\Libs\Model\ModuleMobiModel;
use function GuzzleHttp\json_encode;
use YZ\Core\Site\Site;
use YZ\Core\Model\FileModel;

/**
 *
 * Class TemplateMobiHelper
 * @package App\Modules\ModuleShop\Libs\TemplateMobi
 */
class TemplateMobiHelper
{
    /**
     * 将某个模板安装某个页面上
     *
     * @param [int] $tplId 模板ID
     * @param [int] $siteId 网站ID
     * @param [int] $pageId 页面ID
     * @return void
     */
    public static function installTemplate($tplId,$siteId,$pageId){
        $page = PageMobiModel::where(['site_id' => $siteId,'id' => $pageId])->first();
        if(!$page) throw new \Exception("页面不存在");
        $template = TemplateMobiModel::find($tplId);
        if(!$template) throw new \Exception("模板不存在");
        //读取模板信息
        $tplPage = PageMobiModel::find($template->page_id);
        //读取模板页面的模块数据
        $tplModules = ModuleMobiModel::where(['page_id' => $template->page_id])->get();
        //将模块数据内的图片地址提取出来，方便后面进行复制和替换
        $strParams = [];
        $newFiles = [];
        if($tplModules){
            $tplModules = $tplModules->toArray();
            foreach($tplModules as $index => $m){
                if($m['type'] == 'ModuleProductList') {
                    $m['params'] = json_decode($m['params'],true);
                    $m['params']['param_data_source'] = 0; //将商品来源重设为按分类
                    $m['params']['param_class_ids'] = []; //分类ID为空
                    $m['params'] = json_encode($m['params']);
                    $tplModules[$index]['params'] = $m['params'];
                }
                preg_match_all('@"([^"]*comdata[^"]+)@',$m['params'],$matchs,PREG_PATTERN_ORDER);
                foreach($matchs[1] as $file){
                    if(!$file) continue;
                    $file = stripslashes($file);
                    $oldSitePath = Site::getSiteComdataDir($tplPage->site_id);
                    $newSitePath = Site::getSiteComdataDir($siteId);
                    $newFile = str_ireplace($oldSitePath.'/',$newSitePath.'/',$file);
                    //复制模板用到的文件
                    $src = public_path().(substr($file,0,1) == '/' ? $file : '/'.$file);
                    $dest = public_path().(substr($newFile,0,1) == '/' ? $newFile : '/'.$newFile);
                    if (!file_exists($dest)) {
                        $flag = \Ipower\Common\Util::copyFile($src, $dest);
                        if ($flag) {
                            $oldFile = str_ireplace($oldSitePath, '', $file);
                            $newFiles[$oldFile] = str_ireplace($newSitePath, '', $newFile);
                            $tplModules[$index]['params'] = str_ireplace(addcslashes($file, '/'), addcslashes($newFile, '/'), $m['params']);
                        }
                    }
                }
            }
        }
        //读取原网站的文件记录，用于查找文件名
        $oriFile = FileModel::where(['site_id' => $template->site_id])->get();
        $fileNames = [];
        foreach($oriFile as $f){
            $fileNames[$f->path] = $f->name;
        }
        //新复制的文件添加到资源管理器内
        foreach ($newFiles as $oldfile => $file) {
            $fullFile = Site::getSiteComdataDir($siteId,true).$file;
            $fileInfo = pathinfo($fullFile);
            $fileName = $fileNames[$oldfile];
            if(!$fileName) $fileName = $fileInfo['filename'];
            $fileModel = new FileModel();
            $fileModel->site_id = $siteId;
            $fileModel->folder_id = 0;
            $fileModel->path = $file;
            $fileModel->name = $fileName;
            $fileModel->type = $fileInfo['extension'];
            $fileModel->size = filesize($fullFile);
            $fileModel->save();
        }
        //保存模块数据
        if (is_array($tplModules)) {
            foreach ($tplModules as $m) {
                unset($m['id']);
                unset($m['created_at']);
                $m['site_id'] = $siteId;
                $m['page_id'] = $pageId;
                $row = new ModuleMobiModel();
                $row->forceFill($m);
                $row->save();
            }
        }
        //保存模板页面数据
        if($tplPage->titles) $page->title = $tplPage->title;
        if($tplPage->description) $page->description = $tplPage->description;
        if($tplPage->background) $page->background = $tplPage->background;
        $page->template_id = $tplId;
        $page->save();
    }
}