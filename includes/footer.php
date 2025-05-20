<footer class="footer mt-auto py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="mb-4">
                    <a class="d-flex align-items-center text-decoration-none" href="index.php">
                        <span class="h4 text-white mb-0"><i class="fas fa-graduation-cap me-2"></i>ELearning</span>
                    </a>
                </div>
                <p class="text-gray-400 mb-4">Empowering learners worldwide with quality English education through innovative online learning experiences.</p>
                <div class="d-flex gap-3">
                    <a href="#" class="footer-link">
                        <i class="fab fa-facebook-f fa-lg"></i>
                    </a>
                    <a href="#" class="footer-link">
                        <i class="fab fa-twitter fa-lg"></i>
                    </a>
                    <a href="#" class="footer-link">
                        <i class="fab fa-instagram fa-lg"></i>
                    </a>
                    <a href="#" class="footer-link">
                        <i class="fab fa-linkedin-in fa-lg"></i>
                    </a>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-6">
                <h5>Quick Links</h5>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <a href="about.php" class="footer-link">About Us</a>
                    </li>
                    <li class="mb-2">
                        <a href="courses.php" class="footer-link">Courses</a>
                    </li>
                    <li class="mb-2">
                        <a href="materials.php" class="footer-link">Materials</a>
                    </li>
                    <li class="mb-2">
                        <a href="resources.php" class="footer-link">Resources</a>
                    </li>
                    <li class="mb-2">
                        <a href="contact.php" class="footer-link">Contact</a>
                    </li>
                </ul>
            </div>
            
            <div class="col-lg-2 col-md-6">
                <h5>Support</h5>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <a href="faq.php" class="footer-link">FAQs</a>
                    </li>
                    <li class="mb-2">
                        <a href="help.php" class="footer-link">Help Center</a>
                    </li>
                    <li class="mb-2">
                        <a href="privacy.php" class="footer-link">Privacy Policy</a>
                    </li>
                    <li class="mb-2">
                        <a href="terms.php" class="footer-link">Terms of Service</a>
                    </li>
                </ul>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <h5>Newsletter</h5>
                <p class="text-gray-400 mb-4">Subscribe to our newsletter for the latest updates and learning resources.</p>
                <form class="mb-3">
                    <div class="input-group">
                        <input type="email" class="form-control" placeholder="Your email address">
                        <button class="btn btn-primary" type="submit">Subscribe</button>
                    </div>
                </form>
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-shield-alt text-success"></i>
                    <small class="text-gray-400">Your email is safe with us. We don't spam.</small>
                </div>
            </div>
        </div>
        
        <hr class="my-4 border-gray-700">
        
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start">
                <p class="text-gray-400 mb-0">&copy; <?php echo date('Y'); ?> ELearning. All rights reserved.</p>
            </div>
            <!-- <div class="col-md-6 text-center text-md-end mt-3 mt-md-0">
                <img src="assets/images/payment-methods.png" alt="Payment Methods" height="24" class="payment-methods">
            </div> -->
        </div>
    </div>
</footer>

<!-- Common JavaScript Files -->
<!-- jQuery (if not already included) -->
<script src="/js/lib/jquery-3.6.0.min.js"></script>

<!-- Search Autocomplete JS -->
<script src="<?= site_url('js/search-autocomplete.js') ?>"></script>