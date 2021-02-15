'use strict';

/**
 * Auto complete config
 */
var autoCompleteInst = new autoComplete({
    data: {
        src: autoCompleteData,
        key: ['id'],
    },
    placeHolder: 'Search',
    selector: '#autoCompleteSearch',
    observer: true,
    threshold: 1,
    maxResults: 50,
    highlight: true,
    resultItem: {
        content: (data, source) => {
            source.innerHTML = `
            <div class="d-flex flex-row align-items-center">
                <div>${data.match}</div>
                <span class="text-php ms-2" style="font-size: .65rem;">${data.value.elementType}</span>
            </div>
            `.trim();
        },
        element: 'li',
    },
    noResults: (dataFeedback, generateList) => {
        // Generate autoComplete List
        generateList(autoCompleteInst, dataFeedback, dataFeedback.results);
        // No Results List Item
        var result = document.createElement("li");
        result.setAttribute("class", "no_result");
        result.setAttribute("tabindex", "1");
        result.innerHTML = `<span class="d-flex align-items-center py-1 px-2" style="font-weight: 100; color: rgba(0,0,0,.65);">Found No Results for "${dataFeedback.query}"</span>`;
        document.querySelector(`#${autoCompleteInst.resultsList.idName}`).appendChild(result);
    },
    onSelection: function(feedback) {
        var selection = feedback.selection;

        if (selection && selection.value && selection.value.link && selection.value.link !== '#') {
            window.location.href = selection.value.link;
        }
    },
});

/**
 * Tree config
 */
var tree = new Tree(document.getElementById('tree'), {
    navigate: true // allow navigate with ArrowUp and ArrowDown
});

tree.json(treeData);

tree.on('select', (e) => {
    if (e.dataset.type === 'file' && e.getAttribute('href') !== '#') {
        window.location.href = e.getAttribute('href');

        return;
    }

    if (e.tagName === 'A' && e.classList.contains('summary') && e.getAttribute('href') !== '#') {
        console.log('e.dataset.type', e.dataset.type);
        window.location.href = e.getAttribute('href');

        return;
    }
});

var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
  return new bootstrap.Tooltip(tooltipTriggerEl)
})

// const myResizer = new Resizer('.main-row');
