<?php
/**
 * 用于导出数据库记录
 * User: 李耀辉
 */

namespace YZ\Core\Common;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Style;
use YZ\Core\Logger\Log;

class Export implements FromCollection, WithHeadings, WithEvents
{
    protected $list = null;     // 要导出的记录
    protected $headings = [];   // 导出的第一行 head
    protected $fileName = '';   // 导出文件名称
    protected $mergeList = [];  // 要合并的单元格
    protected $styleList = [];  // 要添加样式的单元格

    /**
     * Export constructor.
     * @param Collection $collection 要导出的数据
     * @param string $fileName 要导出的文件名
     * @param array $headings 导出的第一行 head
     */
    public function __construct(Collection $collection, $fileName = '', $headings = [])
    {
        $this->list = $collection;
        $this->headings = $headings;
        $this->fileName = $fileName ?: str_random() . '.xlsx'; // 默认导出文件名
        $this->setExportFileName($fileName);
    }

    /**
     * 设置要合并的单元格
     * @param array $mergeCell 型如：['B1:B2','C2:C4']
     */
    public function setMerge(array $mergeCell)
    {
        $this->mergeList = $mergeCell;
    }

    /**
     * 设置要合并的单元格
     * @param array $styleCell 型如：['B1:B2','C2:C4']
     */
    public function setStyle(array $styleCell)
    {
        $this->styleList = $styleCell;
    }

    /**
     * 导出的第一行 head
     * @param array $headings
     */
    public function setExportHeadings($headings = [])
    {
        $this->headings = $headings;
    }

    /**
     * 设置导出的文件名
     * @param string $fileName 导出的文件名，有后缀
     */
    public function setExportFileName($fileName = '')
    {
        $this->fileName = $fileName ?: $this->fileName;
    }

    public function collection()
    {
        return $this->list;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    /**
     * 注册事件  现在只做了合并单元格
     * @return array
     */
    public function registerEvents(): array
    {
        if ($this->customRegisterEvents) {
            return ($this->customRegisterEvents)();
        }
        if (!empty($this->mergeList)) {
            return [
                AfterSheet::class => function (AfterSheet $event) {
                    foreach ($this->mergeList as $m) {
                        $event->sheet->getDelegate()->mergeCells($m);
                        $event->sheet->getStyle($m)->getAlignment()->applyFromArray([
                            'vertical' => Alignment::VERTICAL_CENTER,
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                        ]);
                    }
                }
            ];
        } elseif (!empty($this->styleList)) {
            return [
                AfterSheet::class => function (AfterSheet $event) {
                    foreach ($this->styleList as $sheet => $style) {
                       // $event->sheet->getDelegate()->getStyle($sheet)->applyFromArray($style);
                        $event->sheet->getStyle($sheet)->applyFromArray($style);
                    }
                }
            ];
        } else {
            return [];
        }
    }

    /**
     * 注册自定义事件
     * @return array
     */
    public function setRegisterEvents(callable $func)
    {
        $this->customRegisterEvents = $func;
    }

    /**
     * 执行导出
     * @param string $fileName 导出的文件名
     * @return \Maatwebsite\Excel\BinaryFileResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function export($fileName = '')
    {
        $this->setExportFileName($fileName);
        $this->registerEvents();
        return Excel::download($this, $this->fileName);
    }
}