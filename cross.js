  (function() {
    const css = `
      body {
        margin: 0; padding: 0; background-color: #000;
        display: flex; flex-direction: column; justify-content: center;
        align-items: center; height: 100vh; overflow: hidden;
        font-family: 'Avenir', sans-serif; position: relative;
      }
      .logo {
        width: 300px; height: 300px;
        background-image: url('https://iili.io/BpDnXON.png');
        background-size: contain; background-position: center;
        background-repeat: no-repeat;
        filter: drop-shadow(0 0 10px #c00);
        cursor: pointer; z-index: 1000; position: relative;
      }
      .message {
        margin-top: 20px; color: #e0d9d0; font-size: 36px;
        font-weight: 300; letter-spacing: 1px;
        text-shadow: 0 0 8px #000, 0 0 12px #500, 0 0 20px #600, 0 0 30px #700;
        text-align: center; z-index: 1001; position: relative;
        user-select: none;
      }
      @keyframes fadeInPopup { 0% { opacity: 0; transform: scale(0.95); } 100% { opacity: 0.6; transform: scale(1); } }
      @keyframes fadeOutPopup { 0% { opacity: 0.6; transform: scale(1); } 100% { opacity: 0; transform: scale(0.95); } }
      .window {
        position: absolute; width: 25em; height: 15em;
        background-color: #111; border: 1px solid #c00;
        border-radius: 4px; box-shadow: 0 0 15px #c00, 0 0 5px #600 inset;
        z-index: 5; display: flex; flex-direction: column;
        justify-content: center; align-items: center;
        opacity: 0; animation: fadeInPopup 1s ease-out forwards;
      }
      .window.fade-out { animation: fadeOutPopup 1s ease-in forwards; }
      .popup-image {
        width: 80%; height: 70%;
        background-image: url('https://iili.io/BpbJzOu.png');
        background-size: contain; background-position: center;
        background-repeat: no-repeat; opacity: 0.7;
      }
      .header {
        background-color: #300; color: #f00; padding: 6px;
        display: flex; justify-content: space-between;
        font-size: 12px; font-weight: bold; width: 100%;
      }
      .btn-close {
        cursor: pointer; font-size: 10px; width: 12px;
        text-align: center; background-color: #600; border-radius: 50%;
      }
      .buttons { margin-top: 10px; display: flex; justify-content: space-around; width: 100%; }
      .buttons button {
        background-color: #300; color: #f00; border: 1px solid #600;
        padding: 4px 8px; font-size: 10px; cursor: pointer; border-radius: 2px;
      }
    `;
    const style = document.createElement('style');
    style.textContent = css;
    document.head.appendChild(style);

    const htmlContent = `
      <div class="logo" id="logoElement"></div>
      <div class="message">† was here</div>
      <audio id="sound" src="https://mp3tourl.com/audio/1779055045432-e195009c-64e3-45ca-b450-7f0432ad35d4.mp3" preload="auto"></audio>
    `;
    const container = document.createElement('div');
    container.innerHTML = htmlContent;
    document.body.appendChild(container);

    const audio = document.getElementById('sound');
    const logo = document.getElementById('logoElement');

    window.playSound = function() {
      if (audio) {
        audio.currentTime = 0; // Restart sound from beginning
        audio.play().catch(e => console.log("Audio play failed (interaction required):", e));
      }
    };

    logo.addEventListener('click', window.playSound);

  })();

    const messages = [
      "I'M WATCHING YOU",
      "I CAN SEE YOU",
      "INJECTED BY †",
      "              †             ",
      "TARGET ACQUIRED",
      "BREACHED"
    ];

    function createPopups() {
      for (let i = 0; i < 2; i++) {
        const popup = document.createElement('div');
        popup.className = 'window';
        const randomMessage = messages[Math.floor(Math.random() * messages.length)];
        popup.innerHTML = `
          <div class="header">
            <span class="title">FEED ${randomMessage}</span>
            <span class="btn-close" onclick="this.parentElement.parentElement.remove()">X</span>
          </div>
          <div class="popup-image"></div>
          <div class="buttons">
            <button onclick="createPopups()">kill</button>
            <button onclick="createPopups()">die</button>
          </div>
        `;
        popup.style.top = (Math.random() * 80 + 10) + '%';
        popup.style.left = (Math.random() * 80 + 10) + '%';
        document.body.appendChild(popup);
        setTimeout(() => {
          popup.classList.add('fade-out');
          setTimeout(() => popup.remove(), 1000);
        }, 12500);
      }
    }

    function playSound() {
      const sound = document.getElementById('sound');
      sound.currentTime = 0;
      sound.play().catch(e => console.log("Autoplay blocked: ", e));
    }

    window.addEventListener('load', () => {
      playSound();
      setInterval(createPopups, 1800);
    });
