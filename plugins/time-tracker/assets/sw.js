// Service Worker for IT Time Tracker 15-Minute Task Check-ins
// Enables Chrome background notifications and check-in button clicks even when the web page is closed.

self.addEventListener('install', function(event) {
    self.skipWaiting();
});

self.addEventListener('activate', function(event) {
    event.waitUntil(self.clients.claim());
});

// Listen for background check-in messages from scheduler
self.addEventListener('message', function(event) {
    if (event.data && event.data.type === 'TRIGGER_ACTIVE_TASK_CHECKIN') {
        var task = event.data.task;
        self.registration.showNotification('IT Time Tracker 15-Min Check-in', {
            body: "Are you still working on: '" + task.task_name + "'?",
            icon: 'https://cdn-icons-png.flaticon.com/512/2088/2088617.png',
            tag: 'task-checkin-' + task.id,
            requireInteraction: true,
            data: {
                taskId: task.id,
                checkinToken: task.checkin_token
            },
            actions: [
                { action: 'still_working', title: '▶ Still Working On It' },
                { action: 'finished', title: '✔ Mark as Finished' }
            ]
        });
    }
});

// Handle notification click and action button clicks even when browser window/tab is closed
self.addEventListener('notificationclick', function(event) {
    event.notification.close();

    var taskId = event.notification.data ? event.notification.data.taskId : 0;
    var checkinToken = event.notification.data ? event.notification.data.checkinToken : '';
    var checkinAction = event.action ? event.action : 'still_working';

    if (taskId > 0 && checkinToken) {
        var formData = new URLSearchParams();
        formData.append('action', 'checkin_response');
        formData.append('task_id', taskId);
        formData.append('checkin_token', checkinToken);
        formData.append('checkin_action', checkinAction);

        event.waitUntil(
            fetch('index.php?route=time_tracker', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData.toString()
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (checkinAction === 'finished') {
                    self.registration.showNotification('IT Time Tracker', {
                        body: 'Task marked as finished! Total elapsed time saved.',
                        icon: 'https://cdn-icons-png.flaticon.com/512/2088/2088617.png'
                    });
                }
            })
            .catch(function(err) {
                console.error('Checkin response failed:', err);
            })
        );
    } else {
        event.waitUntil(
            clients.matchAll({ type: 'window' }).then(function(clientList) {
                for (var i = 0; i < clientList.length; i++) {
                    var client = clientList[i];
                    if (client.url.indexOf('time_tracker') !== -1 && 'focus' in client) {
                        return client.focus();
                    }
                }
                if (clients.openWindow) {
                    return clients.openWindow('index.php?route=time_tracker');
                }
            })
        );
    }
});
