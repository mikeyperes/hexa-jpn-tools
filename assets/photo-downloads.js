/*
 * "Save all photos": the photos are fetched when the page opens, so one tap opens the phone's
 * share sheet with every photo ("Save N Images" puts them in the camera roll). Browsers that
 * cannot share files keep only the ZIP link.
 */
(function () {
    document.querySelectorAll('.jpn-photos-download').forEach(function (box) {
        var button = box.querySelector('.jpn-photos-download__save');
        var list = [];
        try { list = JSON.parse(box.getAttribute('data-files') || '[]'); } catch (error) { list = []; }
        if (!button || !list.length || !window.File || !navigator.canShare || !navigator.share) {
            return;
        }
        var label = button.textContent;
        var ready = null;
        button.hidden = false;
        button.disabled = true;
        button.textContent = box.getAttribute('data-preparing') || label;
        Promise.all(list.map(function (item) {
            return fetch(item.url, { credentials: 'same-origin' }).then(function (response) {
                if (!response.ok) { throw new Error(String(response.status)); }
                return response.blob();
            }).then(function (blob) {
                return new File([blob], item.name, { type: blob.type || 'image/jpeg' });
            });
        })).then(function (files) {
            if (!navigator.canShare({ files: files })) { throw new Error('share'); }
            ready = files;
            button.disabled = false;
            button.textContent = label;
        }).catch(function () {
            button.hidden = true;
        });
        button.addEventListener('click', function () {
            if (ready) { navigator.share({ files: ready }).catch(function () {}); }
        });
    });
})();
