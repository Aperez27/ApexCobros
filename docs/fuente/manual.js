// Numera capítulos y secciones y arma el índice con enlaces (los enlaces siguen funcionando en el PDF).
(function () {
    const indice = document.getElementById('indice');
    const lista = document.createElement('ol');
    let cap = 0, sec = 0, actual = null;
    document.querySelectorAll('main h1, main h2').forEach((h) => {
        if (h.closest('.portada') || h.dataset.sinIndice !== undefined) return;
        if (h.tagName === 'H1') {
            cap++; sec = 0;
            h.id = 'cap' + cap;
            h.innerHTML = '<span class="num">' + cap + '.</span>' + h.innerHTML;
            const li = document.createElement('li');
            li.innerHTML = '<a href="#' + h.id + '"><span class="n">' + cap + '.</span><b></b></a>';
            li.querySelector('b').textContent = h.textContent.replace(/^\d+\./, '');
            actual = document.createElement('ol');
            li.appendChild(actual);
            lista.appendChild(li);
        } else if (actual && h.dataset.indice !== 'no') {
            sec++;
            h.id = 'cap' + cap + '-' + sec;
            h.textContent = cap + '.' + sec + '  ' + h.textContent;
            const li = document.createElement('li');
            li.innerHTML = '<a href="#' + h.id + '"><span class="n"></span><span></span></a>';
            li.querySelector('.n').textContent = cap + '.' + sec;
            li.querySelector('span:last-child').textContent = h.textContent.replace(/^[\d.]+\s+/, '');
            actual.appendChild(li);
        }
    });
    if (indice) indice.appendChild(lista);
    document.querySelectorAll('[data-fecha]').forEach((e) => {
        const m = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        const d = new Date();
        e.textContent = m[d.getMonth()] + ' de ' + d.getFullYear();
    });
})();
