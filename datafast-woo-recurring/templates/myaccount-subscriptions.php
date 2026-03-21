<?php
if (! defined('ABSPATH')) { exit; }
$subscriptions = $subscriptions ?? [];
?>
<section class="dfwr-account-section dfwr-account-subscriptions">
<div class="dfwr-account-card">
<h3><?php esc_html_e('Suscripciones Datafast', 'datafast-woo-recurring'); ?></h3>
<table class="shop_table shop_table_responsive dfwr-account-table dfwr-subscriptions-table">
<thead><tr><th>ID</th><th>Estado</th><th>Monto</th><th>Próximo cobro</th><th>Retries</th><th></th></tr></thead>
<tbody>
<?php foreach ($subscriptions as $sub) : ?>
<tr>
<td><?php echo esc_html((string) $sub['id']); ?></td>
<td><?php echo esc_html((string) $sub['status']); ?></td>
<td><?php echo esc_html((string) $sub['amount'] . ' ' . $sub['currency']); ?></td>
<td><?php echo esc_html((string) $sub['next_payment_at']); ?></td>
<td><?php echo esc_html((string) $sub['retry_count'] . '/' . $sub['max_retries']); ?></td>
<td>
<?php if ($sub['status'] === 'active') : ?>
<a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['dfwr_cancel_subscription' => $sub['id']]), 'dfwr_cancel_sub_' . $sub['id'])); ?>"><?php esc_html_e('Cancelar', 'datafast-woo-recurring'); ?></a>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</section>
