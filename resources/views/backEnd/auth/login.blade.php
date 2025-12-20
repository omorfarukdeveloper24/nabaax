<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sleepy Lamp Login - NABAAX</title>
    <style>
        :root {
            --bg-dark: #0a0a0a;
            --lamp-off: #222;
            --lamp-on: #ffdb58;
            --light-glow: rgba(255, 225, 100, 0.4);
            --form-glass: rgba(255, 255, 255, 0.03);
        }

        body {
            margin: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background: var(--bg-dark);
            transition: background 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: 'Poppins', sans-serif;
            overflow: hidden;
        }

        body.light-on {
            background: radial-gradient(circle at center, #1a2a3a 0%, #050505 100%);
        }

        
        .lamp-box {
            position: absolute;
            top: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            perspective: 1000px;
        }

        .hanging-wire {
            width: 3px;
            height: 60px;
            background: linear-gradient(to right, #000, #333, #000);
        }

        .lamp-shade {
            width: 160px;
            height: 120px;
            background: var(--lamp-off);
            clip-path: polygon(15% 0%, 85% 0%, 100% 100%, 0% 100%);
            position: relative;
            z-index: 5;
            transition: 0.4s;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        body.light-on .lamp-shade {
            background: var(--lamp-on);
            box-shadow: 0 40px 100px var(--light-glow);
        }

        
        .emoji-face {
            position: relative;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            opacity: 0.8;
        }

        .eyes { display: flex; gap: 40px; margin-top: 10px; }
        
      
        .eye { font-size: 14px; font-weight: bold; color: #555; transition: 0.3s; }
        .eye::before { content: "Z z"; position: absolute; top: 15px; }

        
        .mouth {
            width: 40px;
            height: 20px;
            border-bottom: 3px solid #444;
            border-radius: 0 0 50% 50%;
            margin-top: 10px;
            transition: 0.3s;
        }

        
        body.light-on .eye { color: #000; transform: scale(1.2); }
        body.light-on .eye::before { content: "O O"; font-size: 16px; top: 25px; }
        body.light-on .mouth { border-bottom-color: #000; width: 50px; }

        .pull-string-wrapper {
            position: absolute;
            right: 35px;
            top: 120px;
            cursor: pointer;
            z-index: 10;
        }

        .string {
            width: 2px;
            height: 130px;
            background: #666;
            transition: height 0.15s ease-out;
        }

        .bead {
            width: 14px;
            height: 14px;
            background: #fff;
            border-radius: 50%;
            margin-left: -6px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.5);
        }

        .pull-string-wrapper.active .string { height: 170px; }

        /* লগইন কার্ড */
        .login-card {
            width: 350px;
            padding: 50px 40px;
            background: var(--form-glass);
            border-radius: 24px;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            text-align: center;
            color: #fff;
            opacity: 0;
            transform: translateY(60px);
            transition: 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
            pointer-events: none;
        }

        body.light-on .login-card {
            opacity: 1;
            transform: translateY(0);
            pointer-events: all;
        }

        h2 { font-weight: 600; margin-bottom: 30px; }
        input {
            width: 100%; padding: 14px; margin-bottom: 15px;
            background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px; color: #fff; outline: none; box-sizing: border-box;
        }
        button {
            width: 100%; padding: 14px; background: #ffdb58;
            border: none; border-radius: 10px; font-weight: bold; cursor: pointer;
        }

        @keyframes swing {
            0%, 100% { transform: rotate(0deg); }
            50% { transform: rotate(5deg); }
        }

        .swing-anim { animation: swing 0.8s ease-in-out; }

    </style>
</head>
<body>

    <div class="lamp-box" id="lampBox">
        <div class="hanging-wire"></div>
        <div class="lamp-shade">
            <div class="emoji-face">
                <div class="eyes">
                    <div class="eye"></div>
                    <div class="eye"></div>
                </div>
                <div class="mouth"></div>
            </div>
        </div>
        <div class="pull-string-wrapper" id="pullControl">
            <div class="string"></div>
            <div class="bead"></div>
        </div>
    </div>

    <div class="login-card">
        <h2>NABAAX LOGIN</h2>
        <form method="POST" action="{{ route('auth.login') }}">
            @csrf
        <input type="text" name="email" placeholder="Username or Email" required>
        <input type="password" id="password" name="password" placeholder="Password" required>
        <button>Login</button>
        </form>
    </div>
    
    

    <script>
        const pullControl = document.getElementById('pullControl');
        const lampBox = document.getElementById('lampBox');
        const body = document.body;

        pullControl.addEventListener('mousedown', () => {
            pullControl.classList.add('active');
            
            lampBox.classList.remove('swing-anim');
            void lampBox.offsetWidth; 
            lampBox.classList.add('swing-anim');

            body.classList.toggle('light-on');

            setTimeout(() => {
                pullControl.classList.remove('active');
            }, 150);
        });
    </script>
</body>
</html>
