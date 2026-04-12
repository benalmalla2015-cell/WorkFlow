import React, { useState, useEffect } from 'react';
import { Card, Row, Col, Statistic, Table, Tag, Button, Modal, InputNumber, message, Spin } from 'antd';
import { 
  UserOutlined, 
  ShoppingCartOutlined, 
  DollarOutlined, 
  CheckCircleOutlined,
  EyeOutlined
} from '@ant-design/icons';
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer, PieChart, Pie, Cell } from 'recharts';
import { useAuth } from '../../contexts/AuthContext';
import axios from 'axios';

const AdminDashboard = () => {
  const [stats, setStats] = useState({});
  const [orders, setOrders] = useState([]);
  const [selectedOrder, setSelectedOrder] = useState(null);
  const [modalVisible, setModalVisible] = useState(false);
  const [profitMarginModal, setProfitMarginModal] = useState(false);
  const [loading, setLoading] = useState(false);
  const [marginForm, setMarginForm] = useState({});

  useEffect(() => {
    fetchDashboardStats();
    fetchRecentOrders();
  }, []);

  const fetchDashboardStats = async () => {
    try {
      const response = await axios.get('/api/admin/dashboard/stats');
      setStats(response.data);
    } catch (error) {
      message.error('Failed to fetch dashboard statistics');
    }
  };

  const fetchRecentOrders = async () => {
    try {
      const response = await axios.get('/api/admin/orders?per_page=10');
      setOrders(response.data.data);
    } catch (error) {
      message.error('Failed to fetch recent orders');
    }
  };

  const approveOrder = async (order) => {
    setSelectedOrder(order);
    setMarginForm({ profit_margin_percentage: order.profit_margin_percentage || 20 });
    setProfitMarginModal(true);
  };

  const handleApproveOrder = async () => {
    try {
      setLoading(true);
      await axios.post(`/api/orders/${selectedOrder.id}/approve`, marginForm);
      message.success('Order approved successfully');
      setProfitMarginModal(false);
      setSelectedOrder(null);
      fetchDashboardStats();
      fetchRecentOrders();
    } catch (error) {
      message.error(error.response?.data?.message || 'Failed to approve order');
    } finally {
      setLoading(false);
    }
  };

  const viewOrder = (order) => {
    setSelectedOrder(order);
    setModalVisible(true);
  };

  const getStatusColor = (status) => {
    const colors = {
      draft: 'default',
      factory_pricing: 'processing',
      manager_review: 'warning',
      approved: 'success',
      customer_approved: 'blue',
      payment_confirmed: 'green',
      completed: 'purple',
    };
    return colors[status] || 'default';
  };

  const getStatusText = (status) => {
    return status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
  };

  const COLORS = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042', '#8884D8'];

  const orderColumns = [
    {
      title: 'Order Number',
      dataIndex: 'order_number',
      key: 'order_number',
    },
    {
      title: 'Customer',
      dataIndex: ['customer', 'full_name'],
      key: 'customer',
    },
    {
      title: 'Sales Person',
      dataIndex: ['salesUser', 'name'],
      key: 'salesUser',
    },
    {
      title: 'Amount',
      dataIndex: 'final_price',
      key: 'final_price',
      render: (price) => price ? `$${Number(price).toFixed(2)}` : '-',
    },
    {
      title: 'Status',
      dataIndex: 'status',
      key: 'status',
      render: (status) => (
        <Tag color={getStatusColor(status)}>
          {getStatusText(status)}
        </Tag>
      ),
    },
    {
      title: 'Actions',
      key: 'actions',
      render: (_, record) => (
        <Button.Group>
          <Button 
            size="small" 
            icon={<EyeOutlined />} 
            onClick={() => viewOrder(record)}
          >
            View
          </Button>
          {record.status === 'manager_review' && (
            <Button 
              size="small" 
              type="primary" 
              onClick={() => approveOrder(record)}
            >
              Approve
            </Button>
          )}
        </Button.Group>
      ),
    },
  ];

  return (
    <div style={{ padding: '24px' }}>
      <h2>Admin Dashboard</h2>
      
      {/* Statistics Cards */}
      <Row gutter={16} style={{ marginBottom: '24px' }}>
        <Col span={6}>
          <Card>
            <Statistic
              title="Total Orders"
              value={stats.total_orders || 0}
              prefix={<ShoppingCartOutlined />}
              valueStyle={{ color: '#1890ff' }}
            />
          </Card>
        </Col>
        <Col span={6}>
          <Card>
            <Statistic
              title="Total Revenue"
              value={stats.total_revenue || 0}
              prefix={<DollarOutlined />}
              precision={2}
              valueStyle={{ color: '#52c41a' }}
              formatter={(value) => `$${Number(value).toLocaleString()}`}
            />
          </Card>
        </Col>
        <Col span={6}>
          <Card>
            <Statistic
              title="Pending Orders"
              value={stats.pending_orders || 0}
              prefix={<CheckCircleOutlined />}
              valueStyle={{ color: '#faad14' }}
            />
          </Card>
        </Col>
        <Col span={6}>
          <Card>
            <Statistic
              title="Active Users"
              value={stats.active_users || 0}
              prefix={<UserOutlined />}
              valueStyle={{ color: '#722ed1' }}
            />
          </Card>
        </Col>
      </Row>

      {/* Charts */}
      <Row gutter={16} style={{ marginBottom: '24px' }}>
        <Col span={16}>
          <Card title="Monthly Revenue Trend">
            {stats.monthly_stats && (
              <ResponsiveContainer width="100%" height={300}>
                <LineChart data={stats.monthly_stats}>
                  <CartesianGrid strokeDasharray="3 3" />
                  <XAxis dataKey="month" />
                  <YAxis />
                  <Tooltip />
                  <Legend />
                  <Line type="monotone" dataKey="revenue" stroke="#1890ff" name="Revenue" />
                  <Line type="monotone" dataKey="orders" stroke="#52c41a" name="Orders" />
                </LineChart>
              </ResponsiveContainer>
            )}
          </Card>
        </Col>
        <Col span={8}>
          <Card title="Orders by Status">
            {stats.orders_by_status && (
              <ResponsiveContainer width="100%" height={300}>
                <PieChart>
                  <Pie
                    data={Object.entries(stats.orders_by_status).map(([key, value]) => ({
                      name: getStatusText(key),
                      value
                    }))}
                    cx="50%"
                    cy="50%"
                    labelLine={false}
                    label={({ name, percent }) => `${name} ${(percent * 100).toFixed(0)}%`}
                    outerRadius={80}
                    fill="#8884d8"
                    dataKey="value"
                  >
                    {Object.entries(stats.orders_by_status).map((entry, index) => (
                      <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                    ))}
                  </Pie>
                  <Tooltip />
                </PieChart>
              </ResponsiveContainer>
            )}
          </Card>
        </Col>
      </Row>

      {/* Recent Orders */}
      <Card title="Recent Orders">
        <Table
          columns={orderColumns}
          dataSource={orders}
          rowKey="id"
          pagination={false}
        />
      </Card>

      {/* Order Details Modal */}
      <Modal
        title="Order Details"
        visible={modalVisible}
        onCancel={() => setModalVisible(false)}
        footer={null}
        width={800}
      >
        {selectedOrder && (
          <div>
            <Row gutter={16}>
              <Col span={12}>
                <p><strong>Order Number:</strong> {selectedOrder.order_number}</p>
                <p><strong>Customer:</strong> {selectedOrder.customer?.full_name}</p>
                <p><strong>Phone:</strong> {selectedOrder.customer?.phone}</p>
                <p><strong>Sales Person:</strong> {selectedOrder.salesUser?.name}</p>
              </Col>
              <Col span={12}>
                <p><strong>Product:</strong> {selectedOrder.product_name}</p>
                <p><strong>Quantity:</strong> {selectedOrder.quantity}</p>
                <p><strong>Factory Cost:</strong> ${selectedOrder.factory_cost || 'N/A'}</p>
                <p><strong>Final Price:</strong> ${selectedOrder.final_price || 'N/A'}</p>
              </Col>
            </Row>
            {selectedOrder.specifications && (
              <div style={{ marginTop: '16px' }}>
                <strong>Specifications:</strong>
                <p>{selectedOrder.specifications}</p>
              </div>
            )}
          </div>
        )}
      </Modal>

      {/* Profit Margin Modal */}
      <Modal
        title="Approve Order - Set Profit Margin"
        visible={profitMarginModal}
        onOk={handleApproveOrder}
        onCancel={() => setProfitMarginModal(false)}
        confirmLoading={loading}
      >
        {selectedOrder && (
          <div>
            <p><strong>Order:</strong> {selectedOrder.order_number}</p>
            <p><strong>Product:</strong> {selectedOrder.product_name}</p>
            <p><strong>Factory Cost:</strong> ${selectedOrder.factory_cost}</p>
            <p><strong>Default Margin:</strong> 20%</p>
            
            <div style={{ marginTop: '16px' }}>
              <label><strong>Profit Margin (%):</strong></label>
              <InputNumber
                min={0}
                max={100}
                step={0.1}
                precision={1}
                value={marginForm.profit_margin_percentage}
                onChange={(value) => setMarginForm({ ...marginForm, profit_margin_percentage: value })}
                style={{ width: '100%', marginTop: '8px' }}
              />
            </div>
            
            <div style={{ marginTop: '16px', padding: '12px', backgroundColor: '#f0f0f0', borderRadius: '4px' }}>
              <p><strong>Calculated Final Price:</strong> ${(selectedOrder.factory_cost * (1 + (marginForm.profit_margin_percentage || 20) / 100)).toFixed(2)}</p>
              <p><strong>Profit Amount:</strong> ${(selectedOrder.factory_cost * ((marginForm.profit_margin_percentage || 20) / 100)).toFixed(2)}</p>
            </div>
          </div>
        )}
      </Modal>
    </div>
  );
};

export default AdminDashboard;
