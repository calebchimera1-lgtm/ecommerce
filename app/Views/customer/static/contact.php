<section class="section">
    <div class="container" style="max-width:640px;">
        <div class="section-heading">
            <div class="section-eyebrow">Get in Touch</div>
            <h1 class="section-title">Contact Us</h1>
        </div>
        <form method="POST" action="/contact">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label sans small">Name</label>
                <input type="text" class="form-control" name="name" required value="<?= e(old('name')) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label sans small">Email address</label>
                <input type="email" class="form-control" name="email" required value="<?= e(old('email')) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label sans small">Subject</label>
                <input type="text" class="form-control" name="subject" value="<?= e(old('subject')) ?>">
            </div>
            <div class="mb-4">
                <label class="form-label sans small">Message</label>
                <textarea class="form-control" name="message" rows="5" required><?= e(old('message')) ?></textarea>
            </div>
            <button type="submit" class="btn btn-gold">Send Message</button>
        </form>
    </div>
</section>
