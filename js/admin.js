
const V3D_IGNORE_EXT = [
    'blend',
    'blend1',
    'max',
    'ma',       // maya
    'mb',       // maya
    'mat',      // max material file
    'mel'       // e.g workspace.mel
];

const CHUNK_SIZE = 1000000; // 1MB

const ERROR_CONN = 'Upload failed 🤔 Please check your connection.';
const ERROR_CONN_MIME = ERROR_CONN +' Also ensure that MIME types for app files are whitelisted in Verge3D > Settings > Security.';

async function v3d_handle_uploads(app_id) {

    try {
        const formData = new FormData();
        formData.append('action', 'v3d_cleanup_app');
        formData.append('app', app_id);
        const res = await (await fetch(ajaxurl, { method: 'POST', body: formData })).text();
        if (res != 'ok') {
            alert(ERROR_CONN);
            return;
        }
    } catch {
        alert(ERROR_CONN);
        return;
    }

    const input = document.getElementById('appfiles');
    const progressElem = document.getElementById('upload_progress');
    const statusElem = document.getElementById('upload_status');
    let progressCounter = 0;
    let errorState = false;

    function updateProgress() {
        progressCounter++;
        progressElem.innerText = progressCounter + '/' + input.files.length;

        if (progressCounter == input.files.length) {

            if (errorState) {
                statusElem.className = 'v3d-red';
                statusElem.innerText = 'Error!';
                alert(ERROR_CONN_MIME);
            } else {
                statusElem.className = 'v3d-green';
                statusElem.innerText = 'Success!';
            }

        } else {
            statusElem.innerText = '';
        }
    }

    for (let i = 0; i < input.files.length; i++) {
        const file = input.files[i];
        const path = file.webkitRelativePath || file.name;
        const ext = path.split('.').pop();

        // prevent upload of some files
        if (V3D_IGNORE_EXT.includes(ext) || path.indexOf('v3d_app_data') > -1) {
            updateProgress();
            continue;
        }

        console.log('Uploading ' + path);

        if (file.size < CHUNK_SIZE) {
            const formData = new FormData();
            formData.append('action', 'v3d_upload_app_file');
            formData.append('app', app_id);
            formData.append('apppath', path);
            formData.append('appfile', file);

            fetch(ajaxurl, { method: 'POST', body: formData }).then(response => {
                updateProgress();

                response.text().then(text => {
                    if (text != 'ok')
                        errorState = true;
                });

            }).catch(error => {
                errorState = true;
            });

        } else {
            let start = 0;
            let end = CHUNK_SIZE;
            let chunks = [];

            while (start < file.size) {
                let chunk = file.slice(start, end);
                chunks.push(chunk);
                start = end;
                end = start + CHUNK_SIZE;
            }

            chunks.forEach((chunk, index) => {
                let formData = new FormData();
                formData.append('action', 'v3d_upload_app_file');
                formData.append('app', app_id);
                formData.append('apppath', path);
                formData.append('appfile', chunk);
                formData.append('chunk', index);
                formData.append('maxchunks', chunks.length);

                fetch(ajaxurl, { method: 'POST', body: formData }).then(response => {
                    if (index == chunks.length - 1)
                        updateProgress();

                    response.text().then(text => {
                        if (text != 'ok')
                            errorState = true;
                    });

                }).catch(error => {
                    errorState = true;
                });
            });
        }
    }
}
