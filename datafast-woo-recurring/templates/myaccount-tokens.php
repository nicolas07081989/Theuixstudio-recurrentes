<?php
if (! defined('ABSPATH')) { exit; }
$tokens = $tokens ?? [];
?>
<h3><?php esc_html_e('Tarjetas guardadas', 'datafast-woo-recurring'); ?></h3>
<table class="shop_table shop_table_responsive">
<thead><tr><th>ID</th><th>Marca</th><th>Últimos 4</th><th>Expira</th><th></th></tr></thead>
<tbody>
<?php foreach ($tokens as $token) : ?>
<tr>
<td><?php echo esc_html($token['registration_id']); ?></td>
<td><?php echo esc_html((string) ($token['brand'] ?? '')); ?></td>
<td><?php echo esc_html((string) ($token['last4'] ?? '')); ?></td>
<td><?php echo esc_html((string) (($token['expiry_month'] ?? '') . '/' . ($token['expiry_year'] ?? ''))); ?></td>
<td><button class="button dfwr-delete-token" data-id="<?php echo esc_attr((string) $token['id']); ?>"><?php esc_html_e('Eliminar', 'datafast-woo-recurring'); ?></button></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<script>
document.querySelectorAll('.dfwr-delete-token').forEach(function(btn){
 btn.addEventListener('click', function(){
   fetch('<?php echo esc_url_raw(rest_url('datafast/v1/tokens/')); ?>'+btn.dataset.id,{method:'DELETE',headers:{'X-WP-Nonce':'<?php echo esc_js(wp_create_nonce('wp_rest')); ?>'}}).then(()=>location.reload());
 });
});
</script>
