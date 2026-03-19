<?php
namespace DFWR;

use WC_Order;

class Tax_Mapper
{
    public function map(WC_Order $order): array
    {
        $total = (float) $order->get_total();
        $tax = (float) $order->get_total_tax();
        $base_imp = max(0, $total - $tax);
        $base0 = 0.00;

        foreach ($order->get_items() as $item) {
            $line_total = (float) $item->get_total();
            $line_tax = (float) $item->get_total_tax();
            if ($line_tax <= 0.0) {
                $base0 += $line_total;
                $base_imp -= $line_total;
            }
        }

        $base_imp = max(0, $base_imp);
        return [
            'SHOPPER_VAL_BASE0' => number_format($base0, 2, '.', ''),
            'SHOPPER_VAL_BASEIMP' => number_format($base_imp, 2, '.', ''),
            'SHOPPER_VAL_IVA' => number_format($tax, 2, '.', ''),
        ];
    }
}
