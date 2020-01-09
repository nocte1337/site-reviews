<?php defined('WPINC') || die; ?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?= esc_html(get_admin_page_title()); ?></h1>
    <?= $notices; ?>
    <p><?= __('Add-ons extend the functionality of Site Reviews.', 'site-reviews'); ?></p>
    <div class="glsr-addons wp-clearfix">
    <?php
        $template->render('partials/addons/addon', [
            'context' => [
                'description' => __('Allow your site visitors to submit images with their reviews.', 'site-reviews'),
                'link' => 'https://niftyplugins.com/plugins/images/',
                'slug' => 'images',
                'title' => 'Images',
            ],
            'plugin' => 'site-reviews-images/site-reviews-images.php',
        ]);
    ?>
    </div>
</div>
