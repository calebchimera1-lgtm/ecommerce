<?php
$faqs = [
    ['q' => 'How long does shipping take?', 'a' => 'Standard shipping typically takes 5-7 business days. Express and next-day options are available at checkout once the shipping module launches.'],
    ['q' => 'What is your return policy?', 'a' => 'We accept returns within 30 days of delivery for unworn, unused items in original packaging.'],
    ['q' => 'Are your products authentic?', 'a' => 'Yes. Every item in the Kymera Collection is sourced directly from the brand or an authorized partner.'],
    ['q' => 'Do you ship internationally?', 'a' => 'International shipping options are being finalized and will be announced soon.'],
    ['q' => 'How do I track my order?', 'a' => 'Once order tracking launches, you will be able to follow your order in real time from your account dashboard.'],
];
?>
<section class="section">
    <div class="container" style="max-width:760px;">
        <div class="section-heading">
            <div class="section-eyebrow">Help Center</div>
            <h1 class="section-title">Frequently Asked Questions</h1>
        </div>
        <div class="accordion" id="faqAccordion">
            <?php foreach ($faqs as $index => $faq): ?>
                <div class="accordion-item bg-transparent border-secondary">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed bg-transparent text-white" type="button"
                                data-bs-toggle="collapse" data-bs-target="#faq<?= $index ?>">
                            <?= e($faq['q']) ?>
                        </button>
                    </h2>
                    <div id="faq<?= $index ?>" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-white-50"><?= e($faq['a']) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
