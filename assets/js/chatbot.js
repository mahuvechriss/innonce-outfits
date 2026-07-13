var chatOpen = false;
var chatBusy = false;

function toggleChat() {
    var w = document.getElementById('innoceshow-window');
    var b = document.getElementById('innoceshow-btn');
    chatOpen = !chatOpen;
    w.style.display = chatOpen ? 'flex' : 'none';
    b.style.display = chatOpen ? 'none' : 'flex';
    if (chatOpen) {
        document.getElementById('chat-input').focus();
        scrollChat();
    }
}

function sendChat() {
    var input = document.getElementById('chat-input');
    var msg = input.value.trim();
    if (!msg || chatBusy) return;
    input.value = '';
    addMessage(msg, 'user');
    chatBusy = true;
    var loader = addLoader();
    var xhr = new XMLHttpRequest();
    xhr.open('POST', SITE_URL + '/includes/chatbot-handler.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        removeElement(loader);
        chatBusy = false;
        if (xhr.status === 200) {
            try {
                var data = JSON.parse(xhr.responseText);
                addMessage(data.reply || 'Sorry, I could not process that.', 'bot');
                if (data.products && data.products.length > 0) {
                    addProductCards(data.products);
                }
                if (data.categories && data.categories.length > 0) {
                    addCategoryList(data.categories);
                }
            } catch(e) {
                addMessage('Sorry, something went wrong.', 'bot');
            }
        } else {
            addMessage('Connection error. Please try again.', 'bot');
        }
        scrollChat();
    };
    xhr.onerror = function() {
        removeElement(loader);
        chatBusy = false;
        addMessage('Connection error. Please try again.', 'bot');
        scrollChat();
    };
    xhr.send('message=' + encodeURIComponent(msg));
}

function addMessage(text, type) {
    var container = document.getElementById('chat-messages');
    var div = document.createElement('div');
    div.className = 'chat-msg chat-' + type;
    div.style.cssText = type === 'user'
        ? 'align-self:flex-end;background:linear-gradient(135deg,#FF8C00,#FF6600);border-radius:12px 0 12px 12px;padding:10px 14px;max-width:85%;font-size:13px;line-height:1.5;color:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.1)'
        : 'align-self:flex-start;background:#fff;border-radius:0 12px 12px 12px;padding:10px 14px;max-width:85%;font-size:13px;line-height:1.5;color:#333;box-shadow:0 1px 3px rgba(0,0,0,0.06)';
    div.innerHTML = type === 'user' ? escapeHtml(text) : formatBotMessage(text);
    container.appendChild(div);
    scrollChat();
}

function addProductCards(products) {
    var container = document.getElementById('chat-messages');
    var wrapper = document.createElement('div');
    wrapper.style.cssText = 'align-self:flex-start;width:100%;max-width:85%;margin:4px 0';
    products.forEach(function(p) {
        var card = document.createElement('a');
        card.href = p.link;
        card.target = '_blank';
        card.style.cssText = 'display:flex;align-items:center;gap:10px;background:#fff;border:1px solid #eee;border-radius:10px;padding:8px 10px;margin-bottom:6px;text-decoration:none;color:#333;transition:box-shadow 0.2s';
        card.onmouseover = function() { card.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)'; };
        card.onmouseout = function() { card.style.boxShadow = 'none'; };

        var img = document.createElement('img');
        img.src = p.image;
        img.alt = p.name;
        img.style.cssText = 'width:50px;height:50px;border-radius:8px;object-fit:cover;flex-shrink:0';
        img.onerror = function() { this.src = SITE_URL + '/assets/images/placeholder.png'; };
        card.appendChild(img);

        var info = document.createElement('div');
        info.style.cssText = 'flex:1;min-width:0';

        var name = document.createElement('div');
        name.textContent = p.name;
        name.style.cssText = 'font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis';
        info.appendChild(name);

        if (p.category) {
            var cat = document.createElement('div');
            cat.textContent = p.category;
            cat.style.cssText = 'font-size:11px;color:#999';
            info.appendChild(cat);
        }

        var priceRow = document.createElement('div');
        priceRow.style.cssText = 'display:flex;align-items:center;gap:6px;margin-top:2px';

        var price = document.createElement('span');
        price.textContent = p.price_formatted;
        price.style.cssText = 'font-weight:700;font-size:13px;color:#FF8C00';
        priceRow.appendChild(price);

        if (p.original_price_formatted) {
            var orig = document.createElement('span');
            orig.textContent = p.original_price_formatted;
            orig.style.cssText = 'font-size:11px;color:#bbb;text-decoration:line-through';
            priceRow.appendChild(orig);
        }

        if (p.colors && p.colors.length > 0) {
            p.colors.slice(0, 4).forEach(function(c) {
                var dot = document.createElement('span');
                dot.style.cssText = 'display:inline-block;width:9px;height:9px;border-radius:50%;background:' + c.hex + ';border:1px solid #ddd;margin-left:2px';
                priceRow.appendChild(dot);
            });
        }

        info.appendChild(priceRow);
        card.appendChild(info);

        var arrow = document.createElement('span');
        arrow.textContent = '›';
        arrow.style.cssText = 'font-size:18px;color:#ccc;flex-shrink:0';
        card.appendChild(arrow);

        wrapper.appendChild(card);
    });
    container.appendChild(wrapper);
    scrollChat();
}

function addCategoryList(categories) {
    var container = document.getElementById('chat-messages');
    var wrapper = document.createElement('div');
    wrapper.style.cssText = 'align-self:flex-start;width:100%;max-width:85%;margin:4px 0';
    categories.forEach(function(c) {
        var item = document.createElement('div');
        item.style.cssText = 'background:#fff;border:1px solid #eee;border-radius:8px;padding:8px 12px;margin-bottom:4px;font-size:13px;color:#333';
        item.textContent = c.name + ' (' + c.count + ' products)';
        wrapper.appendChild(item);
    });
    container.appendChild(wrapper);
    scrollChat();
}

function addLoader() {
    var container = document.getElementById('chat-messages');
    var div = document.createElement('div');
    div.id = 'chat-loader';
    div.style.cssText = 'align-self:flex-start;background:#fff;border-radius:0 12px 12px 12px;padding:12px 18px;font-size:13px;color:#999;box-shadow:0 1px 3px rgba(0,0,0,0.06)';
    div.innerHTML = '<span class="chat-dot-pulse"><span></span><span></span><span></span></span>';
    container.appendChild(div);
    scrollChat();
    return div;
}

function removeElement(el) {
    if (el && el.parentNode) el.parentNode.removeChild(el);
}

function scrollChat() {
    var container = document.getElementById('chat-messages');
    container.scrollTop = container.scrollHeight;
}

function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function formatBotMessage(text) {
    text = escapeHtml(text);
    text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    text = text.replace(/\*(.*?)\*/g, '<em>$1</em>');
    text = text.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener" style="color:#FF8C00;text-decoration:underline">$1</a>');
    text = text.replace(/https?:\/\/[^\s<]+/g, '<a href="$&" target="_blank" rel="noopener" style="color:#FF8C00;text-decoration:underline">$&</a>');
    text = text.replace(/\n/g, '<br>');
    return text;
}

(function() {
    var style = document.createElement('style');
    style.textContent = '.chat-dot-pulse { display:inline-flex;align-items:center;gap:4px } .chat-dot-pulse span { width:6px;height:6px;border-radius:50%;background:#FF8C00;animation:chatPulse 1.2s infinite } .chat-dot-pulse span:nth-child(2) { animation-delay:0.2s } .chat-dot-pulse span:nth-child(3) { animation-delay:0.4s } @keyframes chatPulse { 0%,80%,100% { opacity:0.3;transform:scale(0.8) } 40% { opacity:1;transform:scale(1) } }';
    document.head.appendChild(style);
})();
