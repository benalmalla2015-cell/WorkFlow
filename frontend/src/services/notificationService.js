import { initializeApp } from 'firebase/app';
import { getMessaging, getToken, onMessage } from 'firebase/messaging';
import axios from 'axios';

// Firebase configuration - using the credentials provided
const firebaseConfig = {
  apiKey: "AIzaSyAXvcw2kyDn83T1_pI6x7ECJR0RY3ISgy0",
  authDomain: "dayancosys.firebaseapp.com",
  projectId: "dayancosys",
  storageBucket: "dayancosys.appspot.com",
  messagingSenderId: "291737712580",
  appId: "1:291737712580:web:fe48dcdf63780b44de1691"
};

const VAPID_KEY = "BK5H4757WY8PCEtilPjhe4JWipeUSbca4WOkz5sg279nG1gpwxH7cQWMxjxBdq_E7EoP5oCHH325Af7OzDNvAK4";

let app = null;
let messaging = null;

// Sound for notifications
const notificationSound = new Audio('/notification-sound.mp3');

export const initFirebase = async () => {
  try {
    app = initializeApp(firebaseConfig);
    messaging = getMessaging(app);
    
    // Handle foreground messages
    onMessage(messaging, (payload) => {
      console.log('Message received in foreground:', payload);
      
      // Play notification sound
      notificationSound.play().catch(e => console.log('Audio play failed:', e));
      
      // Show notification
      if (Notification.permission === 'granted') {
        new Notification(payload.notification.title, {
          body: payload.notification.body,
          icon: '/logo192.png',
          badge: '/badge.png',
          data: payload.data,
          requireInteraction: true,
          tag: payload.data?.type || 'default',
        });
      }
      
      // Dispatch custom event for React components
      window.dispatchEvent(new CustomEvent('new-notification', { 
        detail: payload 
      }));
    });
    
    return messaging;
  } catch (error) {
    console.error('Firebase initialization error:', error);
    return null;
  }
};

export const requestNotificationPermission = async () => {
  try {
    const permission = await Notification.requestPermission();
    
    if (permission === 'granted') {
      const token = await getFCMToken();
      return token;
    }
    
    return null;
  } catch (error) {
    console.error('Notification permission error:', error);
    return null;
  }
};

export const getFCMToken = async () => {
  try {
    if (!messaging) {
      await initFirebase();
    }
    
    const token = await getToken(messaging, { 
      vapidKey: VAPID_KEY 
    });
    
    if (token) {
      // Register token with backend
      await axios.post('/api/notifications/token', {
        token,
        device_type: 'web'
      });
      
      console.log('FCM Token registered');
    }
    
    return token;
  } catch (error) {
    console.error('FCM token error:', error);
    return null;
  }
};

export const removeFCMToken = async (token) => {
  try {
    await axios.delete('/api/notifications/token', {
      data: { token }
    });
  } catch (error) {
    console.error('Remove FCM token error:', error);
  }
};

export const getNotifications = async () => {
  try {
    const response = await axios.get('/api/notifications');
    return response.data;
  } catch (error) {
    console.error('Get notifications error:', error);
    return [];
  }
};

export const markNotificationAsRead = async (notificationId) => {
  try {
    await axios.post(`/api/notifications/${notificationId}/read`);
  } catch (error) {
    console.error('Mark notification as read error:', error);
  }
};

export const getUnreadCount = async () => {
  try {
    const response = await axios.get('/api/notifications/unread-count');
    return response.data.unread_count || 0;
  } catch (error) {
    console.error('Get unread count error:', error);
    return 0;
  }
};

// Initialize on page load
if (typeof window !== 'undefined') {
  // Check if user is logged in before initializing
  const token = localStorage.getItem('token');
  if (token && 'Notification' in window) {
    initFirebase();
  }
}
