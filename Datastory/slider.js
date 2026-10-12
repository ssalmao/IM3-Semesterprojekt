/**
 * Macht einen Slider bedienbar.
 * sliderElement: das äussere Element mit der Klasse .slider.
 * Alle Teile werden nur innerhalb dieses Elements gesucht,
 * darum kann es mehrere Slider auf der Seite geben.
 */
export function initSlider(sliderElement) {
    const track = sliderElement.querySelector('.slider-track');
    const slides = sliderElement.querySelectorAll('.slide');
    const prevButton = sliderElement.querySelector('.slider-prev');
    const nextButton = sliderElement.querySelector('.slider-next');
    const title = sliderElement.querySelector('.slider-title');
    const count = sliderElement.querySelector('.slider-count');

    let currentSlide = 0;

    function showSlide(index) {
        currentSlide = index;
        track.style.transform = `translateX(-${index * 100}%)`;
        title.textContent = slides[index].dataset.title;
        count.textContent = `${index + 1} / ${slides.length}`;
        prevButton.disabled = index === 0;
        nextButton.disabled = index === slides.length - 1;
    }

    prevButton.addEventListener('click', () => showSlide(currentSlide - 1));
    nextButton.addEventListener('click', () => showSlide(currentSlide + 1));

    showSlide(0);
}