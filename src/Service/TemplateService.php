<?php

namespace App\Service;

use App\Entity\Template;
use App\Repository\TemplateRepository;
use Doctrine\ORM\EntityManagerInterface;

class TemplateService
{
    public function __construct(
        private EntityManagerInterface $em,
        private TemplateRepository $templateRepository,
    ) {}

    public function create(string $name, ?string $subject = null, array $content = [], string $category = 'general'): Template
    {
        $template = new Template();
        $template->setName($name);
        $template->setSubject($subject);
        $template->setContent($content);
        $template->setCategory($category);
        $template->setHtmlContent($this->compileHtml($content));

        $this->em->persist($template);
        $this->em->flush();

        return $template;
    }

    public function update(Template $template, array $data): Template
    {
        if (isset($data['name'])) {
            $template->setName($data['name']);
        }
        if (array_key_exists('subject', $data)) {
            $template->setSubject($data['subject']);
        }
        if (isset($data['content'])) {
            $template->setContent($data['content']);
            $template->setHtmlContent($this->compileHtml($data['content']));
        }
        if (isset($data['category'])) {
            $template->setCategory($data['category']);
        }
        if (isset($data['isDefault'])) {
            $template->setIsDefault($data['isDefault']);
        }

        $template->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $template;
    }

    public function delete(Template $template): void
    {
        $this->em->remove($template);
        $this->em->flush();
    }

    public function compileHtml(array $content): string
    {
        $blocksHtml = '';
        foreach ($content as $block) {
            $blocksHtml .= $this->renderBlock($block);
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Email</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style type="text/css">
        body { margin: 0; padding: 0; width: 100%; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table { border-collapse: collapse; }
        img { border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        .container { max-width: 600px; margin: 0 auto; }
        @media only screen and (max-width: 620px) {
            .container { width: 100% !important; padding: 0 10px !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f7;">
        <tr>
            <td align="center" style="padding: 20px 0;">
                <table role="presentation" class="container" width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <tr>
                        <td style="padding: 0;">
                            {$blocksHtml}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    private function renderBlock(array $block): string
    {
        $type = $block['type'] ?? '';

        return match ($type) {
            'header' => $this->renderHeader($block),
            'text' => $this->renderText($block),
            'image' => $this->renderImage($block),
            'button' => $this->renderButton($block),
            'divider' => $this->renderDivider($block),
            'spacer' => $this->renderSpacer($block),
            'social' => $this->renderSocial($block),
            'html' => $this->renderRawHtml($block),
            default => '',
        };
    }

    private function renderHeader(array $block): string
    {
        $text = htmlspecialchars($block['text'] ?? '', ENT_QUOTES, 'UTF-8');
        $level = $block['level'] ?? 1;
        $align = $block['align'] ?? 'left';

        $sizes = [1 => '28px', 2 => '24px', 3 => '20px', 4 => '18px', 5 => '16px', 6 => '14px'];
        $fontSize = $sizes[$level] ?? '28px';

        return <<<HTML
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding: 30px 40px 10px 40px; text-align: {$align};">
                                        <h{$level} style="margin: 0; font-size: {$fontSize}; font-weight: 700; color: #1a1a2e; line-height: 1.3;">{$text}</h{$level}>
                                    </td>
                                </tr>
                            </table>
HTML;
    }

    private function renderText(array $block): string
    {
        $content = htmlspecialchars($block['content'] ?? '', ENT_QUOTES, 'UTF-8');
        $align = $block['align'] ?? 'left';

        return <<<HTML
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding: 10px 40px; text-align: {$align};">
                                        <p style="margin: 0; font-size: 16px; line-height: 1.6; color: #4a4a68;">{$content}</p>
                                    </td>
                                </tr>
                            </table>
HTML;
    }

    private function renderImage(array $block): string
    {
        $src = htmlspecialchars($block['src'] ?? '', ENT_QUOTES, 'UTF-8');
        $alt = htmlspecialchars($block['alt'] ?? '', ENT_QUOTES, 'UTF-8');
        $width = $block['width'] ?? '100%';
        $align = $block['align'] ?? 'center';
        $link = $block['link'] ?? null;

        $imgHtml = "<img src=\"{$src}\" alt=\"{$alt}\" width=\"{$width}\" style=\"display: block; max-width: 100%; height: auto;\">";
        if ($link) {
            $linkEscaped = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
            $imgHtml = "<a href=\"{$linkEscaped}\" target=\"_blank\">{$imgHtml}</a>";
        }

        return <<<HTML
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding: 10px 40px; text-align: {$align};">
                                        {$imgHtml}
                                    </td>
                                </tr>
                            </table>
HTML;
    }

    private function renderButton(array $block): string
    {
        $text = htmlspecialchars($block['text'] ?? 'Click Here', ENT_QUOTES, 'UTF-8');
        $url = htmlspecialchars($block['url'] ?? '#', ENT_QUOTES, 'UTF-8');
        $color = $block['color'] ?? '#ffffff';
        $bgColor = $block['bgColor'] ?? '#6366f1';
        $align = $block['align'] ?? 'center';

        return <<<HTML
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding: 20px 40px; text-align: {$align};">
                                        <table role="presentation" cellpadding="0" cellspacing="0" style="display: inline-block;">
                                            <tr>
                                                <td style="background-color: {$bgColor}; border-radius: 6px; padding: 14px 30px;">
                                                    <a href="{$url}" target="_blank" style="color: {$color}; text-decoration: none; font-size: 16px; font-weight: 600; display: inline-block;">{$text}</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
HTML;
    }

    private function renderDivider(array $block): string
    {
        $color = $block['color'] ?? '#e2e8f0';
        $thickness = $block['thickness'] ?? '1';

        return <<<HTML
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding: 10px 40px;">
                                        <hr style="border: none; border-top: {$thickness}px solid {$color}; margin: 0;">
                                    </td>
                                </tr>
                            </table>
HTML;
    }

    private function renderSpacer(array $block): string
    {
        $height = $block['height'] ?? '20';

        return <<<HTML
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding: 0; height: {$height}px; line-height: {$height}px; font-size: 1px;">&nbsp;</td>
                                </tr>
                            </table>
HTML;
    }

    private function renderSocial(array $block): string
    {
        $networks = $block['networks'] ?? [];
        $linksHtml = '';
        foreach ($networks as $network) {
            $name = htmlspecialchars($network['name'] ?? '', ENT_QUOTES, 'UTF-8');
            $url = htmlspecialchars($network['url'] ?? '#', ENT_QUOTES, 'UTF-8');
            $linksHtml .= "<a href=\"{$url}\" target=\"_blank\" style=\"color: #6366f1; text-decoration: none; font-size: 14px; margin: 0 10px;\">{$name}</a>";
        }

        return <<<HTML
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding: 20px 40px; text-align: center;">
                                        {$linksHtml}
                                    </td>
                                </tr>
                            </table>
HTML;
    }

    private function renderRawHtml(array $block): string
    {
        $content = strip_tags($block['content'] ?? '', '<p><br><a><strong><em><ul><ol><li><h1><h2><h3><h4><h5><h6><span><div><table><tr><td><th><thead><tbody><img><hr>');

        return <<<HTML
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding: 10px 40px;">
                                        {$content}
                                    </td>
                                </tr>
                            </table>
HTML;
    }

    public function preview(array $content): string
    {
        return $this->compileHtml($content);
    }

    public function duplicate(Template $template): Template
    {
        $copy = new Template();
        $copy->setName($template->getName() . ' (Copy)');
        $copy->setSubject($template->getSubject());
        $copy->setContent($template->getContent());
        $copy->setHtmlContent($template->getHtmlContent());
        $copy->setCategory($template->getCategory());
        $copy->setIsDefault(false);

        $this->em->persist($copy);
        $this->em->flush();

        return $copy;
    }
}
