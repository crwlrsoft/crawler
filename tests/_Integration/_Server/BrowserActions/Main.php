<!doctype html>
<html lang="de">
<head>
    <meta charset=utf-8>
    <title>Hello World</title>
    <style>
        #mouseover_check_1 { position: absolute; top: 200px; right: 100px; }
        #mouseover_check_2 { position: absolute; top: 400px; left:300px; }
        #scroll_down_check { position: absolute; top: 4000px; }
        #scroll_up_check { position: absolute; top: 2000px; }
    </style>
</head>
<body>
<div>
    <div id="click_el_wrapper"></div>
    <div id="shadow_host"></div>
    <div id="evaluation_container"></div>
    <div id="input_wrapper">
        <div id="input_value"></div>
        <input type="text" id="input" />
    </div>
    <div id="mouseover_check_1">mouse wasn't here yet</div>
    <div id="mouseover_check_2">mouse wasn't here yet</div>
    <div id="scroll_up_check">not scrolled up yet</div>
    <div id="scroll_down_check">not scrolled down yet</div>

    <script>
        setTimeout(function () {
            document.getElementById('click_el_wrapper').innerHTML = '<div id="click_worked"></div>' + "\n" +
                '<div id="click_element" onclick="document.getElementById(\'click_worked\').innerHTML = \'yes\'">' +
                'Click me</div>';
        }, 200);
        const shadowHost = document.getElementById('shadow_host');
        const shadowDom = shadowHost.attachShadow({ mode: 'open' });
        const shadowClickDiv = document.createElement('div');
        shadowClickDiv.id = 'shadow_click_div';
        shadowClickDiv.innerHTML = 'Not clicked yet';
        shadowClickDiv.addEventListener('click', function () {
            this.innerHTML = 'clicked';
        }, false);
        shadowDom.appendChild(shadowClickDiv);
        document.getElementById('mouseover_check_1').addEventListener('mouseover', function () {
            this.innerHTML = 'mouse was here';
        });
        document.getElementById('mouseover_check_2').addEventListener('mouseover', function () {
            this.innerHTML = 'mouse was here';
        });
        document.addEventListener('scroll', function () {
            const elementIsVisibleInViewport = (el, partiallyVisible = false) => {
                const { top, left, bottom, right } = el.getBoundingClientRect();
                const { innerHeight, innerWidth } = window;
                return partiallyVisible
                    ? ((top > 0 && top < innerHeight) ||
                        (bottom > 0 && bottom < innerHeight)) &&
                    ((left > 0 && left < innerWidth) || (right > 0 && right < innerWidth))
                    : top >= 0 && left >= 0 && bottom <= innerHeight && right <= innerWidth;
            };

            const scrollDownCheckEl = document.getElementById('scroll_down_check');
            const scrollUpCheckEl = document.getElementById('scroll_up_check');

            if (elementIsVisibleInViewport(scrollDownCheckEl, true) && scrollDownCheckEl.innerHTML !== 'scrolled down') {
                scrollDownCheckEl.innerHTML = 'scrolled down';
            }

            if (
                elementIsVisibleInViewport(scrollUpCheckEl, true) &&
                scrollDownCheckEl.innerHTML === 'scrolled down' &&
                scrollUpCheckEl.innerHTML !== 'scrolled up'
            ) {
                scrollUpCheckEl.innerHTML = 'scrolled up';
            }
        }, false);
        document.getElementById('input').addEventListener('input', function (ev) {
            document.getElementById('input_value').innerHTML = document.getElementById('input').value;
        }, false);
    </script>
</div>
</body>
</html>
