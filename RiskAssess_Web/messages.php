<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php");
    exit;
}
require_once 'config.php';

$user_id = $_SESSION['user_id'];

// Get counselors the user has had appointments with
$stmt = $conn->prepare("
    SELECT DISTINCT c.id, c.user_id, u.name, c.profile_photo, c.specialties
    FROM appointments a
    JOIN counsellors c ON a.counsellor_id = c.id
    JOIN users u ON c.user_id = u.id
    WHERE a.client_id = ?
    ORDER BY u.name
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$counselors_result = $stmt->get_result();
$counselors = [];
while ($row = $counselors_result->fetch_assoc()) {
    $counselors[] = $row;
}
$stmt->close();

// Get unread message counts for each counselor
$unread_counts = [];
foreach ($counselors as $counselor) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM messages
        WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
    ");
    $stmt->bind_param("ii", $counselor['user_id'], $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    $unread_counts[$counselor['id']] = $count;
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - RiskAssess</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #007bff;
            --primary-light: #e6f2ff;
            --primary-dark: #0056b3;
            --secondary: #6c757d;
            --success: #28a745;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #343a40;
            --border: #e5e7eb;
            --shadow: 0 4px 20px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fb;
            color: #333;
        }
        
        .chat-container {
            height: calc(100vh - 100px);
            background-color: #fff;
            border-radius: 16px;
            box-shadow: var(--shadow);
            overflow: hidden;
            display: flex;
        }
        
        /* Sidebar Styles */
        .chat-sidebar {
            width: 320px;
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            background-color: #fff;
            transition: var(--transition);
        }
        
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .sidebar-title {
            font-size: 1.25rem;
            margin: 0;
        }
        
        .search-container {
            padding: 15px;
            border-bottom: 1px solid var(--border);
        }
        
        .search-input {
            width: 100%;
            padding: 10px 15px;
            border-radius: 50px;
            border: 1px solid var(--border);
            background-color: #f5f7fb;
            transition: var(--transition);
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }
        
        .counselor-list {
            flex: 1;
            overflow-y: auto;
            padding: 0;
            margin: 0;
            list-style: none;
        }
        
        .counselor-item {
            padding: 15px 20px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid #f1f1f1;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .counselor-item:hover {
            background-color: #f8fafd;
        }
        
        .counselor-item.active {
            background-color: var(--primary-light);
            border-left: 3px solid var(--primary);
        }
        
        .counselor-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .counselor-info {
            flex: 1;
            overflow: hidden;
        }
        
        .counselor-name {
            font-weight: 500;
            margin-bottom: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .counselor-specialty {
            font-size: 0.85rem;
            color: var(--secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .unread-badge {
            background-color: var(--primary);
            color: white;
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 50px;
            margin-left: 8px;
        }
        
        /* Chat Area Styles */
        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            background-color: #f5f7fb;
        }
        
        .chat-header {
            padding: 15px 20px;
            background-color: #fff;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .chat-header-info {
            margin-left: 15px;
        }
        
        .chat-header-name {
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .chat-header-specialty {
            font-size: 0.85rem;
            color: var(--secondary);
        }
        
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }
        
        .message-date {
            text-align: center;
            margin: 15px 0;
            position: relative;
        }
        
        .message-date span {
            background-color: #f5f7fb;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            color: var(--secondary);
            position: relative;
            z-index: 1;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .message {
            max-width: 75%;
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
            animation: fadeIn 0.3s ease;
        }
        
        .message.outgoing {
            align-self: flex-end;
        }
        
        .message-bubble {
            padding: 12px 16px;
            border-radius: 18px;
            position: relative;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            word-break: break-word;
        }
        
        .message.incoming .message-bubble {
            background-color: #fff;
            border-bottom-left-radius: 5px;
            margin-left: 15px;
        }
        
        .message.outgoing .message-bubble {
            background-color: var(--primary);
            color: white;
            border-bottom-right-radius: 5px;
            margin-right: 15px;
        }
        
        .message-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
            position: absolute;
            bottom: 0;
        }
        
        .message.incoming .message-avatar {
            left: -45px;
        }
        
        .message.outgoing .message-avatar {
            right: -45px;
        }
        
        .message-time {
            font-size: 0.75rem;
            margin-top: 5px;
            opacity: 0.7;
        }
        
        .message.incoming .message-time {
            margin-left: 15px;
            color: var(--secondary);
        }
        
        .message.outgoing .message-time {
            margin-right: 15px;
            color: var(--primary-dark);
            align-self: flex-end;
        }
        
        .message-status {
            margin-left: 5px;
        }
        
        .chat-input-container {
            padding: 15px 20px;
            background-color: #fff;
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
        }
        
        .chat-input {
            flex: 1;
            padding: 12px 15px;
            border-radius: 24px;
            border: 1px solid var(--border);
            background-color: #f5f7fb;
            resize: none;
            max-height: 120px;
            transition: var(--transition);
        }
        
        .chat-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }
        
        .chat-send {
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 10px;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .chat-send:hover {
            background-color: var(--primary-dark);
            transform: scale(1.05);
        }
        
        .chat-send:disabled {
            background-color: var(--secondary);
            cursor: not-allowed;
            transform: none;
        }
        
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            padding: 20px;
            text-align: center;
            color: var(--secondary);
        }
        
        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
            color: var(--primary);
            opacity: 0.7;
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Responsive Styles */
        @media (max-width: 992px) {
            .chat-container {
                height: calc(100vh - 80px);
            }
        }
        
        @media (max-width: 768px) {
            .chat-container {
                flex-direction: column;
            }
            
            .chat-sidebar {
                width: 100%;
                height: 40%;
                border-right: none;
                border-bottom: 1px solid var(--border);
            }
            
            .chat-main {
                height: 60%;
            }
            
            .message {
                max-width: 85%;
            }
        }
        
        @media (max-width: 576px) {
            .chat-container {
                border-radius: 0;
                height: calc(100vh - 60px);
            }
            
            .message {
                max-width: 90%;
            }
            
            .message-avatar {
                width: 30px;
                height: 30px;
            }
            
            .message.incoming .message-avatar {
                left: -35px;
            }
            
            .message.outgoing .message-avatar {
                right: -35px;
            }
        }
        
        /* Mobile optimization */
        @media (max-width: 480px) {
            .counselor-avatar {
                width: 40px;
                height: 40px;
            }
            
            .chat-header {
                padding: 10px 15px;
            }
            
            .chat-messages {
                padding: 15px 10px;
            }
            
            .chat-input-container {
                padding: 10px 15px;
            }
            
            .chat-send {
                width: 40px;
                height: 40px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="chat-container">
            <!-- Sidebar -->
            <div class="chat-sidebar">
                <div class="sidebar-header">
                    <h2 class="sidebar-title">Messages</h2>
                    <?php if (array_sum($unread_counts) > 0): ?>
                        <span class="badge bg-primary"><?php echo array_sum($unread_counts); ?> unread</span>
                    <?php endif; ?>
                </div>
                <div class="search-container">
                    <input type="text" class="search-input" id="counselorSearch" placeholder="Search counselors...">
                </div>
                <ul class="counselor-list" id="counselorList">
                    <?php if (empty($counselors)): ?>
                        <li class="empty-state">
                            <div>
                                <i class="fas fa-user-md empty-state-icon"></i>
                                <h3>No counselors found</h3>
                                <p>You haven't had any appointments with counselors yet.</p>
                            </div>
                        </li>
                    <?php else: ?>
                        <?php foreach ($counselors as $counselor): ?>
                            <li class="counselor-item" data-id="<?php echo $counselor['id']; ?>" data-user-id="<?php echo $counselor['user_id']; ?>">
                                <img src="<?php echo !empty($counselor['profile_photo']) ? 'uploads/counsellors/' . $counselor['profile_photo'] : 'assets/img/default-profile.png'; ?>" alt="<?php echo htmlspecialchars($counselor['name']); ?>" class="counselor-avatar">
                                <div class="counselor-info">
                                    <div class="counselor-name">
                                        <?php echo htmlspecialchars($counselor['name']); ?>
                                        <?php if ($unread_counts[$counselor['id']] > 0): ?>
                                            <span class="unread-badge"><?php echo $unread_counts[$counselor['id']]; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="counselor-specialty"><?php echo htmlspecialchars($counselor['specialties']); ?></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
            
            <!-- Chat Area -->
            <div class="chat-main">
                <div class="empty-state" id="emptyChatState">
                    <i class="fas fa-comments empty-state-icon"></i>
                    <h3>Select a counselor to start chatting</h3>
                    <p>Choose a counselor from the list to view your conversation history and send messages.</p>
                </div>
                
                <div id="chatContent" style="display: none; height: 100%; display: flex; flex-direction: column;">
                    <div class="chat-header" id="chatHeader">
                        <img src="assets/img/default-profile.png" alt="Counselor" class="counselor-avatar" id="chatHeaderAvatar">
                        <div class="chat-header-info">
                            <div class="chat-header-name" id="chatHeaderName"></div>
                            <div class="chat-header-specialty" id="chatHeaderSpecialty"></div>
                        </div>
                    </div>
                    
                    <div class="chat-messages" id="chatMessages"></div>
                    
                    <div class="chat-input-container">
                        <textarea class="chat-input" id="messageInput" placeholder="Type a message..." rows="1"></textarea>
                        <button class="chat-send" id="sendButton" disabled>
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            let currentCounselorId = null;
            let currentCounselorUserId = null;
            let lastMessageId = 0;
            let messageCache = {};
            let isScrolledToBottom = true;
            let fetchingMessages = false;
            let messageCheckInterval = null;
            
            // Auto-resize textarea
            const messageInput = document.getElementById('messageInput');
            messageInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
                $('#sendButton').prop('disabled', this.value.trim() === '');
            });
            
            // Search counselors
            $('#counselorSearch').on('input', function() {
                const searchTerm = $(this).val().toLowerCase();
                $('.counselor-item').each(function() {
                    const counselorName = $(this).find('.counselor-name').text().toLowerCase();
                    const counselorSpecialty = $(this).find('.counselor-specialty').text().toLowerCase();
                    
                    if (counselorName.includes(searchTerm) || counselorSpecialty.includes(searchTerm)) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });
            
            // Select counselor
            $('.counselor-item').click(function() {
                $('.counselor-item').removeClass('active');
                $(this).addClass('active');
                
                currentCounselorId = $(this).data('id');
                currentCounselorUserId = $(this).data('user-id');
                
                // Update chat header
                $('#chatHeaderAvatar').attr('src', $(this).find('.counselor-avatar').attr('src'));
                $('#chatHeaderName').text($(this).find('.counselor-name').text().trim().replace(/\d+ unread$/, ''));
                $('#chatHeaderSpecialty').text($(this).find('.counselor-specialty').text());
                
                // Show chat content
                $('#emptyChatState').hide();
                $('#chatContent').show();
                
                // Load chat messages
                loadChatMessages();
                
                // Start polling for new messages
                if (messageCheckInterval) {
                    clearInterval(messageCheckInterval);
                }
                messageCheckInterval = setInterval(checkNewMessages, 3000);
                
                // Remove unread badge
                $(this).find('.unread-badge').remove();
                
                // Mark messages as read
                markMessagesAsRead();
                
                // Focus on input
                $('#messageInput').focus();
            });
            
            // Load chat messages
            function loadChatMessages() {
                if (!currentCounselorUserId || fetchingMessages) return;
                
                fetchingMessages = true;
                
                $.ajax({
                    url: 'processes/get_messages.php',
                    type: 'GET',
                    data: {
                        counselor_user_id: currentCounselorUserId
                    },
                    dataType: 'json',
                    success: function(response) {
                        fetchingMessages = false;
                        
                        if (response.success) {
                            displayMessages(response.messages);
                            
                            // Update last message ID
                            if (response.messages.length > 0) {
                                lastMessageId = response.messages[response.messages.length - 1].id;
                            }
                            
                            // Scroll to bottom
                            scrollToBottom();
                        } else {
                            console.error('Error loading messages:', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        fetchingMessages = false;
                        console.error('AJAX error:', error);
                    }
                });
            }
            
            // Check for new messages
            function checkNewMessages() {
                if (!currentCounselorUserId || fetchingMessages) return;
                
                fetchingMessages = true;
                
                $.ajax({
                    url: 'processes/get_new_messages.php',
                    type: 'GET',
                    data: {
                        counselor_user_id: currentCounselorUserId,
                        last_message_id: lastMessageId
                    },
                    dataType: 'json',
                    success: function(response) {
                        fetchingMessages = false;
                        
                        if (response.success && response.messages.length > 0) {
                            appendNewMessages(response.messages);
                            
                            // Update last message ID
                            lastMessageId = response.messages[response.messages.length - 1].id;
                            
                            // Mark messages as read
                            markMessagesAsRead();
                            
                            // Scroll to bottom if user was at bottom
                            if (isScrolledToBottom) {
                                scrollToBottom();
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        fetchingMessages = false;
                        console.error('AJAX error:', error);
                    }
                });
            }
            
            // Display messages
            function displayMessages(messages) {
                const chatMessages = $('#chatMessages');
                chatMessages.empty();
                
                let currentDate = '';
                
                messages.forEach(function(message) {
                    // Check if date has changed
                    const messageDate = formatDate(message.date);
                    if (messageDate !== currentDate) {
                        currentDate = messageDate;
                        chatMessages.append(`
                            <div class="message-date">
                                <span>${messageDate}</span>
                            </div>
                        `);
                    }
                    
                    // Determine message direction
                    const isOutgoing = message.sender_id == <?php echo $user_id; ?>;
                    const messageClass = isOutgoing ? 'outgoing' : 'incoming';
                    
                    // Create message element
                    const messageElement = $(`
                        <div class="message ${messageClass}">
                            <div class="message-bubble">
                                ${message.message}
                                <img src="${message.sender_avatar || 'assets/img/default-profile.png'}" alt="Avatar" class="message-avatar">
                            </div>
                            <div class="message-time">
                                ${message.time}
                                ${isOutgoing ? `
                                    <span class="message-status">
                                        ${message.is_read ? '<i class="fas fa-check-double"></i>' : '<i class="fas fa-check"></i>'}
                                    </span>
                                ` : ''}
                            </div>
                        </div>
                    `);
                    
                    chatMessages.append(messageElement);
                });
            }
            
            // Append new messages
            function appendNewMessages(messages) {
                const chatMessages = $('#chatMessages');
                let currentDate = getLastDisplayedDate();
                
                messages.forEach(function(message) {
                    // Check if date has changed
                    const messageDate = formatDate(message.date);
                    if (messageDate !== currentDate) {
                        currentDate = messageDate;
                        chatMessages.append(`
                            <div class="message-date">
                                <span>${messageDate}</span>
                            </div>
                        `);
                    }
                    
                    // Determine message direction
                    const isOutgoing = message.sender_id == <?php echo $user_id; ?>;
                    const messageClass = isOutgoing ? 'outgoing' : 'incoming';
                    
                    // Create message element
                    const messageElement = $(`
                        <div class="message ${messageClass}">
                            <div class="message-bubble">
                                ${message.message}
                                <img src="${message.sender_avatar || 'assets/img/default-profile.png'}" alt="Avatar" class="message-avatar">
                            </div>
                            <div class="message-time">
                                ${message.time}
                                ${isOutgoing ? `
                                    <span class="message-status">
                                        ${message.is_read ? '<i class="fas fa-check-double"></i>' : '<i class="fas fa-check"></i>'}
                                    </span>
                                ` : ''}
                            </div>
                        </div>
                    `);
                    
                    chatMessages.append(messageElement);
                });
            }
            
            // Mark messages as read
            function markMessagesAsRead() {
                if (!currentCounselorUserId) return;
                
                $.ajax({
                    url: 'processes/mark_messages_read.php',
                    type: 'POST',
                    data: {
                        counselor_user_id: currentCounselorUserId
                    },
                    dataType: 'json'
                });
            }
            
            // Send message
            function sendMessage() {
                if (!currentCounselorUserId) return;
                
                const messageText = $('#messageInput').val().trim();
                if (messageText === '') return;
                
                // Clear input
                $('#messageInput').val('');
                $('#messageInput').css('height', 'auto');
                $('#sendButton').prop('disabled', true);
                
                $.ajax({
                    url: 'processes/send_message.php',
                    type: 'POST',
                    data: {
                        counselor_user_id: currentCounselorUserId,
                        message: messageText
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Check for new messages (including the one we just sent)
                            checkNewMessages();
                        } else {
                            console.error('Error sending message:', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', error);
                    }
                });
            }
            
            // Send message on button click
            $('#sendButton').click(function() {
                sendMessage();
            });
            
            // Send message on Enter key
            $('#messageInput').keypress(function(e) {
                if (e.which === 13 && !e.shiftKey) {
                    e.preventDefault();
                    if ($(this).val().trim() !== '') {
                        sendMessage();
                    }
                }
            });
            
            // Check if user is at bottom of chat
            $('#chatMessages').scroll(function() {
                const element = $(this)[0];
                isScrolledToBottom = element.scrollHeight - element.clientHeight <= element.scrollTop + 50;
            });
            
            // Helper function to format date
            function formatDate(dateString) {
                const date = new Date(dateString);
                const today = new Date();
                const yesterday = new Date(today);
                yesterday.setDate(yesterday.getDate() - 1);
                
                if (date.toDateString() === today.toDateString()) {
                    return 'Today';
                } else if (date.toDateString() === yesterday.toDateString()) {
                    return 'Yesterday';
                } else {
                    return date.toLocaleDateString('en-US', { 
                        weekday: 'long', 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric' 
                    });
                }
            }
            
            // Get the last displayed date
            function getLastDisplayedDate() {
                const dateDividers = $('.message-date span');
                if (dateDividers.length === 0) return '';
                
                return dateDividers.last().text();
            }
            
            // Scroll to bottom of chat
            function scrollToBottom() {
                const chatMessages = document.getElementById('chatMessages');
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        });
    </script>
</body>
</html>
