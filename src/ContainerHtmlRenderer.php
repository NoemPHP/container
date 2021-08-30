<?php

declare(strict_types=1);

namespace Noem\Container;

class ContainerHtmlRenderer
{
    public function __construct(private array $report)
    {
    }

    public function render(): string
    {
        $result = '';
        $headerRow = array_shift($this->report);
        $cols = count($headerRow);
        $result .= '<table>';
        $result .= '<thead>';
        $result .= '<tr>';
        foreach ($headerRow as $item) {
            $result .= '<th>' . $item . '</th>';
        }
        $result .= '</tr>';
        $result .= '</thead>';
        $result .= '<tbody>';

        foreach ($this->report as $row) {
            $result .= '<tr>';
            foreach ($row as $item) {
                $result .= '<td>' . $this->cast($item) . '</td>';
            }
            $result .= '</tr>';
        }
        $result .= '</tbody>';
        $result .= '</table>';
        return $result;
    }

    private function cast($item): string
    {
        return match (true) {
            is_array($item) => implode(PHP_EOL, $item),
            default => (string)$item,
        };
    }
}
