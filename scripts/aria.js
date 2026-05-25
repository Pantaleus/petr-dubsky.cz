document.addEventListener('DOMContentLoaded', function () {
  const navLinks = document.querySelectorAll('.main-nav a');

  function updateAriaCurrent() {
    const scrollPos = window.scrollY + 100;

    navLinks.forEach(link => {
      const section = document.querySelector(link.getAttribute('href'));
      if (section) {
        const sectionTop = section.offsetTop;
        const sectionHeight = section.offsetHeight;

        if (scrollPos >= sectionTop && scrollPos < sectionTop + sectionHeight) {
          link.setAttribute('aria-current', 'page');
          link.classList.add('active');
        } else {
          link.removeAttribute('aria-current');
          link.classList.remove('active');
        }
      }
    });
  }

  // Spustit při načtení a scrollování
  window.addEventListener('scroll', updateAriaCurrent);
  updateAriaCurrent(); // Inicializace
});
