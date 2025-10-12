// Плавная прокрутка к секциям
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

// Hover эффекты для карточек (добавление класса при наведении)
document.querySelectorAll('.card').forEach(card => {
    card.addEventListener('mouseenter', () => {
        card.classList.add('hovered');
    });
    card.addEventListener('mouseleave', () => {
        card.classList.remove('hovered');
    });
});

// Инициализация слайдера Bootstrap
const carousel = document.querySelector('.carousel');
if (carousel) {
    new bootstrap.Carousel(carousel, {
        interval: 5000,
        ride: 'carousel'
    });
}

function scrollToSection(sectionId) {
    const section = document.getElementById(sectionId);
    if (section) {
        section.scrollIntoView({ behavior: 'smooth' });
    }
}

window.addEventListener('scroll', () => {
    const scrollUpButton = document.querySelector('.scroll-up');
    if (window.scrollY > 300) {
        scrollUpButton.classList.add('show');
    } else {
        scrollUpButton.classList.remove('show');
    }
});
