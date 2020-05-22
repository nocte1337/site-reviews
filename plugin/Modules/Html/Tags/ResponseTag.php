<?php

namespace GeminiLabs\SiteReviews\Modules\Html\Tags;

use GeminiLabs\SiteReviews\Modules\Html\Builder;

class ResponseTag extends ContentTag
{
    /**
     * {@inheritdoc}
     */
    public function handle($value)
    {
        if (!$this->isHidden() && !empty(trim($value))) {
            $title = sprintf(__('Response from %s', 'site-reviews'), get_bloginfo('name'));
            $text = $this->normalizeText($value);
            $response = glsr(Builder::class)->div($text, [
                'class' => 'glsr-review-response-inner',
                'text' => sprintf('<p><strong>%s</strong></p><p>%s</p>', $title, $text),
            ]);
            $background = glsr(Builder::class)->div([
                'class' => 'glsr-review-response-background',
            ]);
            return $this->wrap($response.$background);
        }
    }
}
