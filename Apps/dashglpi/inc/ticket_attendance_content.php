<?php

// HTML seguro, independente de estilos do conteúdo original (PLAN-20260905-001).
use Glpi\RichText\RichText;

const DASHGLPI_ATTENDANCE_CONTENT_MAX = 200000;

/** Recebe conteúdo nativo GLPI, já decodificado pelo driver GLPI 11. */
function dashglpi_attendance_content(string $content, int $ticketId, array $documents = []): array
{
    $truncated = strlen($content) > DASHGLPI_ATTENDANCE_CONTENT_MAX;
    if ($truncated) {
        $content = mb_strcut($content, 0, DASHGLPI_ATTENDANCE_CONTENT_MAX, 'UTF-8');
    }
    $html = RichText::getSafeHtml($content);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    try {
        $dom->loadHTML('<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>', LIBXML_NONET);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    $allowed = ['p', 'div', 'span', 'br', 'hr', 'strong', 'b', 'em', 'i', 'u', 's', 'del', 'ul', 'ol', 'li',
        'blockquote', 'pre', 'code', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'a', 'img'];
    foreach (array_reverse(iterator_to_array($dom->getElementsByTagName('*'))) as $element) {
        $tag = strtolower($element->tagName);
        if (in_array($tag, ['html', 'body'], true)) {
            continue;
        }
        if (!in_array($tag, $allowed, true)) {
            $element->parentNode?->removeChild($element);
            continue;
        }
        $href = $element->getAttribute('href');
        $src = $element->getAttribute('src');
        $alt = $element->getAttribute('alt');
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $element->removeAttribute($attribute->name);
        }
        if ($tag === 'a' && preg_match('~^https?://|^mailto:~i', $href)) {
            $element->setAttribute('href', $href);
            $element->setAttribute('rel', 'noopener noreferrer');
            $element->setAttribute('target', '_blank');
        }
        if ($tag === 'img') {
            parse_str((string) parse_url($src, PHP_URL_QUERY), $query);
            $documentId = (int) ($query['docid'] ?? $query['id'] ?? 0);
            if ($documentId <= 0 || !isset($documents[$documentId]) || empty($documents[$documentId]['is_image'])) {
                $element->parentNode?->removeChild($element);
                continue;
            }
            $element->setAttribute('src', '../front/document-preview.php?ticket_id=' . $ticketId . '&document_id=' . $documentId);
            $element->setAttribute('alt', $alt ?: 'Imagem do atendimento');
            $element->setAttribute('loading', 'lazy');
        }
    }
    $body = $dom->getElementsByTagName('body')->item(0);
    $safe = '';
    foreach ($body?->childNodes ?? [] as $node) {
        $safe .= $dom->saveHTML($node);
    }
    return ['content_html' => $safe, 'content_text' => RichText::getTextFromHtml($safe, false, true, false, true, true),
        'content_format' => 'sanitized_html', 'content_truncated' => $truncated];
}
