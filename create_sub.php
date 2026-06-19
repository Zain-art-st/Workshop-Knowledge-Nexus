<?php
// create_sub.php
session_start();
?>
<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ScholarSpace - Create Sub-Community</title>

    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="subcommunity.css">
</head>

<body>

    <div class="stars-bg"></div>
    <div class="sunset-bg"></div>

    <div class="auth-page">
        <div class="auth-card" style="max-width: 650px; width: 100%; position: relative; z-index: 2;">

            <div class="auth-header-nav">
                <button type="button" class="close-btn" onclick="window.history.back()">&times;</button>
                <div>
                    <button type="button" id="nextBtn" class="btn btn-primary top-action-btn">Next</button>
                    <button type="button" id="createBtn" class="btn btn-primary top-action-btn hidden">Create
                        Sub</button>
                </div>
            </div>

            <div class="step-indicator">
                <div id="dot1" class="step-dot active"></div>
                <div id="dot2" class="step-dot"></div>
            </div>

            <div id="alertMsg" class="alert-box" style="display: none;"></div>

            <div id="step1">
                <h2 class="auth-card-title" style="font-size: 28px; margin-bottom: 10px;">What is your community about?
                </h2>

                <div class="topic-grid">
                    <div class="topic-card" data-value="Arts">
                        <div class="topic-circle"></div><span class="topic-label">Arts</span>
                    </div>
                    <div class="topic-card" data-value="Education">
                        <div class="topic-circle"></div><span class="topic-label">Education</span>
                    </div>
                    <div class="topic-card" data-value="Music">
                        <div class="topic-circle"></div><span class="topic-label">Music</span>
                    </div>
                    <div class="topic-card" data-value="Computer and technological">
                        <div class="topic-circle"></div><span class="topic-label">Computer and technological</span>
                    </div>
                    <div class="topic-card" data-value="Tips n Trick">
                        <div class="topic-circle"></div><span class="topic-label">Tips n Trick</span>
                    </div>
                    <div class="topic-card" data-value="Nature and outdoors">
                        <div class="topic-circle"></div><span class="topic-label">Nature and outdoors</span>
                    </div>
                    <div class="topic-card" data-value="Collectibles and other hobbies">
                        <div class="topic-circle"></div><span class="topic-label">Collectibles and other hobbies</span>
                    </div>
                    <div class="topic-card" data-value="Food and Drinks">
                        <div class="topic-circle"></div><span class="topic-label">Food and Drinks</span>
                    </div>
                    <div class="topic-card" data-value="Movies and TV">
                        <div class="topic-circle"></div><span class="topic-label">Movies and TV</span>
                    </div>
                    <div class="topic-card" data-value="None of the above">
                        <div class="topic-circle"></div><span class="topic-label">None of the above</span>
                    </div>
                </div>
            </div>

            <div id="step2" class="hidden">
                <h2 class="auth-card-title" style="font-size: 28px; margin-bottom: 4px;">Tell us about your community
                </h2>
                <p style="text-align: center; color: var(--text-muted); font-size: 13px; margin-bottom: 30px;">
                    A name and description help people understand what your community is all about
                </p>

                <div class="form-group">
                    <input type="text" id="communityName" placeholder="Community Name" class="input-round"
                        autocomplete="off">
                </div>

                <div class="form-group">
                    <textarea id="communityDesc" placeholder="Description" rows="5"
                        class="textarea-round"></textarea>
                </div>

                <div class="auth-links" style="margin-top: 15px;">
                    <a href="#" id="backLink">Back to change topic</a>
                </div>
            </div>

        </div>
    </div>

    <script>
        const step1 = document.getElementById('step1');
        const step2 = document.getElementById('step2');
        const nextBtn = document.getElementById('nextBtn');
        const createBtn = document.getElementById('createBtn');
        const backLink = document.getElementById('backLink');
        const dot1 = document.getElementById('dot1');
        const dot2 = document.getElementById('dot2');
        const topicCards = document.querySelectorAll('.topic-card');
        const alertMsg = document.getElementById('alertMsg');

        let communityData = {
            topic: '',
            name: '',
            description: ''
        };

        function showAlert(text, type) {
            alertMsg.innerText = text;
            alertMsg.className = 'alert-box ' + (type === 'success' ? 'alert-success' : 'alert-error');
            alertMsg.style.display = 'block';
        }

        function hideAlert() {
            alertMsg.style.display = 'none';
        }

        topicCards.forEach(card => {
            card.addEventListener('click', function () {
                topicCards.forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                communityData.topic = this.getAttribute('data-value');
                hideAlert();
            });
        });

        nextBtn.addEventListener('click', () => {
            if (!communityData.topic) {
                showAlert('Sila pilih satu topik komuniti terlebih dahulu!', 'error');
                return;
            }
            hideAlert();
            step1.classList.add('hidden');
            step2.classList.remove('hidden');
            nextBtn.classList.add('hidden');
            createBtn.classList.remove('hidden');
            dot1.classList.remove('active');
            dot1.classList.add('done');
            dot2.classList.add('active');
        });

        backLink.addEventListener('click', (e) => {
            e.preventDefault();
            hideAlert();
            step2.classList.add('hidden');
            step1.classList.remove('hidden');
            createBtn.classList.add('hidden');
            nextBtn.classList.remove('hidden');
            dot2.classList.remove('active');
            dot1.classList.remove('done');
            dot1.classList.add('active');
        });

        createBtn.addEventListener('click', () => {
            communityData.name = document.getElementById('communityName').value.trim();
            communityData.description = document.getElementById('communityDesc').value.trim();

            if (!communityData.name) {
                showAlert('Sila masukkan Nama Komuniti!', 'error');
                return;
            }
            if (!communityData.description) {
                showAlert('Sila berikan sedikit deskripsi tentang komuniti anda!', 'error');
                return;
            }

            createBtn.disabled = true;
            createBtn.innerText = 'Creating...';

            const params = new URLSearchParams();
            params.append('topic', communityData.topic);
            params.append('name', communityData.name);
            params.append('description', communityData.description);

            fetch('process_create_sub.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            })
                .then(response => response.json())
                .then(data => {
                    createBtn.disabled = false;
                    createBtn.innerText = 'Create Sub';

                    if (data.status === 'success') {
                        showAlert(data.message, 'success');
                        setTimeout(() => {
                            window.location.href = 'subcommunity.php?id=' + data.id;
                        }, 2000);
                    } else {
                        showAlert(data.message, 'error');
                    }
                })
                .catch(error => {
                    createBtn.disabled = false;
                    createBtn.innerText = 'Create Sub';
                    showAlert('Ralat sistem berlaku. Sila cuba lagi.', 'error');
                    console.error('Error:', error);
                });
        });
    </script>
</body>

</html>