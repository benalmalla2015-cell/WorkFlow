import React, { useState, useEffect, useCallback } from 'react';
import { Badge, Dropdown, List, Typography, Space, Tag, Empty, Spin, Button, Divider } from 'antd';
import { BellOutlined, CheckOutlined, ReloadOutlined } from '@ant-design/icons';
import moment from 'moment';
import {
  getNotifications,
  markNotificationAsRead,
  getUnreadCount,
  requestNotificationPermission,
} from '../services/notificationService';

const { Text, Paragraph } = Typography;

const NotificationBell = () => {
  const [notifications, setNotifications] = useState([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [loading, setLoading] = useState(false);
  const [dropdownOpen, setDropdownOpen] = useState(false);
  const [permissionRequested, setPermissionRequested] = useState(false);

  const loadNotifications = useCallback(async () => {
    setLoading(true);
    try {
      const data = await getNotifications();
      setNotifications(data.data || []);
      const count = await getUnreadCount();
      setUnreadCount(count);
    } catch (error) {
      console.error('Failed to load notifications:', error);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadNotifications();

    // Listen for new notifications
    const handleNewNotification = () => {
      loadNotifications();
    };

    window.addEventListener('new-notification', handleNewNotification);

    // Poll for new notifications every 30 seconds
    const interval = setInterval(() => {
      getUnreadCount().then(setUnreadCount);
    }, 30000);

    return () => {
      window.removeEventListener('new-notification', handleNewNotification);
      clearInterval(interval);
    };
  }, [loadNotifications]);

  const handleEnableNotifications = async () => {
    setPermissionRequested(true);
    await requestNotificationPermission();
  };

  const handleMarkAsRead = async (e, notificationId) => {
    e.stopPropagation();
    try {
      await markNotificationAsRead(notificationId);
      setNotifications(prev => 
        prev.map(n => 
          n.id === notificationId ? { ...n, read_at: new Date().toISOString() } : n
        )
      );
      setUnreadCount(prev => Math.max(0, prev - 1));
    } catch (error) {
      console.error('Failed to mark as read:', error);
    }
  };

  const handleMarkAllAsRead = async () => {
    try {
      const unreadNotifications = notifications.filter(n => !n.read_at);
      await Promise.all(unreadNotifications.map(n => markNotificationAsRead(n.id)));
      setNotifications(prev => 
        prev.map(n => ({ ...n, read_at: n.read_at || new Date().toISOString() }))
      );
      setUnreadCount(0);
    } catch (error) {
      console.error('Failed to mark all as read:', error);
    }
  };

  const getNotificationIcon = (type) => {
    const colors = {
      'adjustment_requested': 'orange',
      'adjustment_resolved': 'green',
      'adjustment_rejected': 'red',
      'order_status_changed': 'blue',
    };
    return colors[type] || 'default';
  };

  const getNotificationTitle = (type) => {
    const titles = {
      'adjustment_requested': 'طلب تعديل',
      'adjustment_resolved': 'تمت الموافقة',
      'adjustment_rejected': 'تم الرفض',
      'order_status_changed': 'تحديث الطلب',
    };
    return titles[type] || 'إشعار';
  };

  const dropdownContent = (
    <div style={{ width: 380, maxHeight: 500, overflow: 'auto' }}>
      <div style={{ padding: '12px 16px', borderBottom: '1px solid #f0f0f0' }}>
        <Space style={{ width: '100%', justifyContent: 'space-between' }}>
          <Text strong style={{ fontSize: 16 }}>الإشعارات</Text>
          <Space>
            {unreadCount > 0 && (
              <Button 
                type="text" 
                size="small" 
                icon={<CheckOutlined />}
                onClick={handleMarkAllAsRead}
              >
                تحديد الكل كمقروء
              </Button>
            )}
            <Button 
              type="text" 
              size="small" 
              icon={<ReloadOutlined />}
              loading={loading}
              onClick={loadNotifications}
            />
          </Space>
        </Space>
      </div>

      {!permissionRequested && 'Notification' in window && Notification.permission === 'default' && (
        <div style={{ padding: '12px 16px', background: '#e6f7ff', borderBottom: '1px solid #91d5ff' }}>
          <Space direction="vertical" style={{ width: '100%' }}>
            <Text type="secondary" style={{ fontSize: 12 }}>
              فعّل الإشعارات لتلقي تنبيهات فورية
            </Text>
            <Button type="primary" size="small" onClick={handleEnableNotifications}>
              تفعيل الإشعارات
            </Button>
          </Space>
        </div>
      )}

      <Spin spinning={loading}>
        {notifications.length === 0 ? (
          <Empty 
            description="لا توجد إشعارات" 
            image={Empty.PRESENTED_IMAGE_SIMPLE}
            style={{ padding: 40 }}
          />
        ) : (
          <List
            dataSource={notifications}
            renderItem={item => (
              <List.Item
                style={{
                  padding: '12px 16px',
                  backgroundColor: item.read_at ? '#fff' : '#f6ffed',
                  cursor: 'pointer',
                  borderBottom: '1px solid #f0f0f0',
                }}
                onClick={() => {
                  if (!item.read_at) {
                    handleMarkAsRead({ stopPropagation: () => {} }, item.id);
                  }
                  if (item.action_url) {
                    window.location.href = item.action_url;
                  }
                }}
                actions={[
                  !item.read_at && (
                    <Button
                      key="read"
                      type="text"
                      size="small"
                      icon={<CheckOutlined />}
                      onClick={(e) => handleMarkAsRead(e, item.id)}
                    />
                  )
                ].filter(Boolean)}
              >
                <List.Item.Meta
                  title={
                    <Space>
                      <Tag color={getNotificationIcon(item.type)}>
                        {getNotificationTitle(item.type)}
                      </Tag>
                      <Text strong={!item.read_at}>{item.title}</Text>
                    </Space>
                  }
                  description={
                    <Space direction="vertical" size={0} style={{ width: '100%' }}>
                      <Paragraph 
                        ellipsis={{ rows: 2 }} 
                        style={{ marginBottom: 4, fontSize: 13 }}
                      >
                        {item.body}
                      </Paragraph>
                      <Text type="secondary" style={{ fontSize: 11 }}>
                        {moment(item.created_at).fromNow()}
                      </Text>
                    </Space>
                  }
                />
              </List.Item>
            )}
          />
        )}
      </Spin>
    </div>
  );

  return (
    <Dropdown
      dropdownRender={() => dropdownContent}
      trigger={['click']}
      open={dropdownOpen}
      onOpenChange={setDropdownOpen}
      placement="bottomRight"
    >
      <Badge count={unreadCount} overflowCount={99}>
        <Button 
          type="text" 
          icon={<BellOutlined style={{ fontSize: 20 }} />}
          style={{ width: 40, height: 40 }}
        />
      </Badge>
    </Dropdown>
  );
};

export default NotificationBell;
