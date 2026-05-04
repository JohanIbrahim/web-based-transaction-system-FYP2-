/**
 * Smart Web-Based Transaction System - Main JavaScript
 * Phase 1: Foundation
 */

document.addEventListener('DOMContentLoaded', function () {

    // --- Mobile Navigation Toggle ---
    const navbarToggle = document.getElementById('navbarToggle');
    const navbarNav = document.getElementById('navbarNav');

    if (navbarToggle && navbarNav) {
        navbarToggle.addEventListener('click', function () {
            navbarNav.classList.toggle('active');
        });

        // Close nav when clicking outside
        document.addEventListener('click', function (e) {
            if (!navbarToggle.contains(e.target) && !navbarNav.contains(e.target)) {
                navbarNav.classList.remove('active');
            }
        });
    }

});
