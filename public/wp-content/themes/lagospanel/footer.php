</main>

<footer class="site-footer">
  <div class="wrap footer-grid">
    <div class="footer-brand">
      <?php echo lagospanel_company_logo(52); ?>
      <p><?php lagos_e('footer_tag'); ?></p>
    </div>
    <div class="footer-col">
      <h4><?php lagos_e('footer_product'); ?></h4>
      <a href="<?php echo esc_url(home_url('/loja/')); ?>"><?php lagos_e('nav_store'); ?></a>
      <a href="<?php echo esc_url(home_url('/#precos')); ?>"><?php lagos_e('nav_pricing'); ?></a>
      <a href="<?php echo esc_url(home_url('/#features')); ?>"><?php lagos_e('nav_features'); ?></a>
      <a href="<?php echo esc_url(home_url('/painel/suporte/')); ?>"><?php lagos_e('menu_support'); ?></a>
      <a href="<?php echo esc_url(home_url('/base-de-conhecimento/')); ?>"><?php lagos_e('kb_title'); ?></a>
      <a href="<?php echo esc_url(home_url('/verificar-dominio/')); ?>"><?php lagos_e('dom_title'); ?></a>
      <a href="<?php echo esc_url(home_url('/downloads/')); ?>"><?php lagos_e('dl_title'); ?></a>
      <a href="<?php echo esc_url(home_url('/status/')); ?>"><?php lagos_e('net_title'); ?></a>
    </div>
    <div class="footer-col">
      <h4><?php lagos_e('footer_company'); ?></h4>
      <a href="#"><?php lagos_e('footer_about'); ?></a>
      <a href="#"><?php lagos_e('footer_contact'); ?></a>
      <a href="<?php echo esc_url(home_url('/painel/')); ?>"><?php lagos_e('nav_login'); ?></a>
      <a href="<?php echo esc_url(home_url('/registrar/')); ?>"><?php lagos_e('nav_register'); ?></a>
    </div>
    <div class="footer-col">
      <h4><?php lagos_e('footer_legal'); ?></h4>
      <a href="<?php echo esc_url(home_url('/termos-de-uso/')); ?>"><?php lagos_e('footer_terms'); ?></a>
      <a href="<?php echo esc_url(home_url('/politica-de-privacidade/')); ?>"><?php lagos_e('footer_privacy'); ?></a>
    </div>
  </div>
  <div class="footer-bottom">
    <div class="wrap">
      <span>© <?php echo esc_html(date_i18n('Y')); ?> <?php lagos_e('brand_tag'); ?> — LagosPanel. <?php lagos_e('footer_rights'); ?></span>
      <span class="footer-made">Feito com <?php echo lagos_icon('heart', 13, 'footer-heart'); ?> no Brasil</span>
    </div>
  </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
