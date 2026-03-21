document.addEventListener('DOMContentLoaded', function() {
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Highlight current section in sidebar
    const sections = document.querySelectorAll('section');
    const navLinks = document.querySelectorAll('nav a');

    function highlightNavigation() {
        let scrollPosition = window.scrollY;

        sections.forEach(section => {
            const sectionTop = section.offsetTop - 100;
            const sectionBottom = sectionTop + section.offsetHeight;
            const sectionId = section.getAttribute('id');

            if (scrollPosition >= sectionTop && scrollPosition < sectionBottom) {
                navLinks.forEach(link => {
                    link.classList.remove('active');
                    if (link.getAttribute('href') === '#' + sectionId) {
                        link.classList.add('active');
                    }
                });
            }
        });
    }

    // Add active class style
    const style = document.createElement('style');
    style.textContent = `
        nav ul li a.active {
            background: #e9ecef;
            color: var(--primary-color);
            font-weight: 500;
        }
    `;
    document.head.appendChild(style);

    window.addEventListener('scroll', highlightNavigation);
    highlightNavigation(); // Initial highlight
}); 