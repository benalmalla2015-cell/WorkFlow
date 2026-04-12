import React, { useState, useEffect, useCallback } from 'react';
import { Card, Row, Col, Statistic, Table, Button, Modal, Form, InputNumber, Tag, Typography, message, Space, Tabs, Select, DatePicker, Spin } from 'antd';
import { CheckCircleOutlined, DollarOutlined, TeamOutlined, FileTextOutlined, ReloadOutlined, EditOutlined, BarChartOutlined } from '@ant-design/icons';
import { Line, Bar, Pie } from 'react-chartjs-2';
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, BarElement, ArcElement, Title as ChartTitle, Tooltip, Legend } from 'chart.js';
import AppLayout from '../../components/AppLayout';
import axios from 'axios';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, ArcElement, ChartTitle, Tooltip, Legend);

const { Title, Text } = Typography;

const statusColors = {
  draft: 'default', factory_pricing: 'processing', manager_review: 'warning',
  approved: 'success', customer_approved: 'cyan', payment_confirmed: 'green', completed: 'purple'
};

export default function AdminDashboard() {
  const [stats, setStats] = useState(null);
  const [orders, setOrders] = useState([]);
  const [profitData, setProfitData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [approveModal, setApproveModal] = useState({ open: false, order: null });
  const [approving, setApproving] = useState(false);
  const [form] = Form.useForm();
  const [activeTab, setActiveTab] = useState('overview');

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const [statsRes, ordersRes, profitRes] = await Promise.all([
        axios.get('/api/admin/dashboard/stats'),
        axios.get('/api/admin/orders'),
        axios.get('/api/admin/profit-analysis'),
      ]);
      setStats(statsRes.data);
      setOrders(ordersRes.data?.data || ordersRes.data || []);
      setProfitData(profitRes.data);
    } catch (e) {
      message.error('Failed to load dashboard');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  const openApprove = (order) => {
    const defaultMargin = stats?.default_profit_margin || 20;
    const suggestedPrice = order.factory_cost ? (parseFloat(order.factory_cost) * (1 + defaultMargin / 100)).toFixed(2) : 0;
    form.setFieldsValue({
      profit_margin_percentage: defaultMargin,
      suggested_price: suggestedPrice,
    });
    setApproveModal({ open: true, order });
  };

  const handleApprove = async (values) => {
    setApproving(true);
    try {
      await axios.post(`/api/orders/${approveModal.order.id}/approve`, {
        profit_margin_percentage: values.profit_margin_percentage,
      });
      message.success(`Order #${approveModal.order.order_number} approved with ${values.profit_margin_percentage}% margin`);
      setApproveModal({ open: false, order: null });
      loadData();
    } catch (err) {
      message.error(err.response?.data?.message || 'Approval failed');
    } finally {
      setApproving(false);
    }
  };

  const pendingOrders = orders.filter(o => o.status === 'manager_review');

  const columns = [
    { title: 'Order #', dataIndex: 'order_number', key: 'order_number', width: 130 },
    {
      title: 'Customer', key: 'customer', width: 140,
      render: (_, r) => r.customer?.full_name || '—'
    },
    { title: 'Product', dataIndex: 'product_name', key: 'product_name', ellipsis: true },
    { title: 'Qty', dataIndex: 'quantity', key: 'quantity', width: 70 },
    {
      title: 'Factory Cost', key: 'factory_cost', width: 120,
      render: (_, r) => r.factory_cost ? `$${parseFloat(r.factory_cost).toFixed(2)}` : '—'
    },
    {
      title: 'Final Price', key: 'final_price', width: 120,
      render: (_, r) => r.final_price ? <Text type="success">${parseFloat(r.final_price).toFixed(2)}</Text> : '—'
    },
    {
      title: 'Margin', key: 'margin', width: 80,
      render: (_, r) => r.profit_margin_percentage ? <Tag color="blue">{r.profit_margin_percentage}%</Tag> : '—'
    },
    {
      title: 'Status', dataIndex: 'status', key: 'status', width: 130,
      render: s => <Tag color={statusColors[s]}>{s?.replace(/_/g, ' ')}</Tag>
    },
    {
      title: 'Actions', key: 'actions', width: 100,
      render: (_, r) => r.status === 'manager_review' ? (
        <Button type="primary" size="small" icon={<CheckCircleOutlined />} onClick={() => openApprove(r)}>
          Approve
        </Button>
      ) : null
    },
  ];

  const chartColors = ['#667eea', '#764ba2', '#f093fb', '#4facfe', '#43e97b', '#f9ca24'];

  const profitChartData = profitData ? {
    labels: profitData.monthly_labels || [],
    datasets: [{
      label: 'Monthly Profit (USD)',
      data: profitData.monthly_profit || [],
      borderColor: '#667eea',
      backgroundColor: 'rgba(102,126,234,0.1)',
      tension: 0.4,
      fill: true,
    }]
  } : null;

  const statusChartData = stats ? {
    labels: Object.keys(stats.orders_by_status || {}),
    datasets: [{
      data: Object.values(stats.orders_by_status || {}),
      backgroundColor: chartColors,
    }]
  } : null;

  if (loading) return <AppLayout><Spin size="large" style={{ display: 'block', margin: '80px auto' }} /></AppLayout>;

  return (
    <AppLayout>
      <div style={{ padding: '24px' }}>
        <Row align="middle" justify="space-between" style={{ marginBottom: 24 }}>
          <Col><Title level={3} style={{ margin: 0 }}>📊 Admin Dashboard</Title></Col>
          <Col>
            <Button icon={<ReloadOutlined />} onClick={loadData}>Refresh</Button>
          </Col>
        </Row>

        {/* Stats Cards */}
        <Row gutter={16} style={{ marginBottom: 24 }}>
          <Col xs={12} sm={6}>
            <Card><Statistic title="Total Orders" value={stats?.total_orders || 0} prefix={<FileTextOutlined />} /></Card>
          </Col>
          <Col xs={12} sm={6}>
            <Card><Statistic title="Pending Approval" value={pendingOrders.length} prefix={<EditOutlined />} valueStyle={{ color: '#faad14' }} /></Card>
          </Col>
          <Col xs={12} sm={6}>
            <Card><Statistic title="Total Revenue" value={stats?.total_revenue || 0} prefix="$" precision={2} valueStyle={{ color: '#52c41a' }} /></Card>
          </Col>
          <Col xs={12} sm={6}>
            <Card><Statistic title="Total Users" value={stats?.total_users || 0} prefix={<TeamOutlined />} /></Card>
          </Col>
        </Row>

        {/* Pending Approvals Alert */}
        {pendingOrders.length > 0 && (
          <Card
            title={<><EditOutlined /> Orders Pending Your Approval ({pendingOrders.length})</>}
            style={{ marginBottom: 24, border: '2px solid #faad14' }}
            headStyle={{ background: '#fffbe6' }}
          >
            <Table
              dataSource={pendingOrders}
              columns={columns.filter(c => ['order_number','customer','product_name','quantity','factory_cost','actions'].includes(c.key))}
              rowKey="id"
              pagination={false}
              size="small"
            />
          </Card>
        )}

        <Tabs activeKey={activeTab} onChange={setActiveTab} items={[
          {
            key: 'overview',
            label: '📋 All Orders',
            children: (
              <Table
                dataSource={orders}
                columns={columns}
                rowKey="id"
                pagination={{ pageSize: 15 }}
                scroll={{ x: 900 }}
                size="small"
              />
            )
          },
          {
            key: 'charts',
            label: '📈 Analytics',
            children: (
              <Row gutter={16}>
                <Col span={16}>
                  <Card title="Monthly Profit Trend">
                    {profitChartData ? (
                      <Line data={profitChartData} options={{ responsive: true, plugins: { legend: { position: 'top' } } }} />
                    ) : <Text type="secondary">No profit data yet</Text>}
                  </Card>
                </Col>
                <Col span={8}>
                  <Card title="Orders by Status">
                    {statusChartData ? (
                      <Pie data={statusChartData} options={{ responsive: true }} />
                    ) : <Text type="secondary">No status data</Text>}
                  </Card>
                </Col>
                {profitData?.employee_performance && (
                  <Col span={24} style={{ marginTop: 16 }}>
                    <Card title="Employee Performance">
                      <Bar
                        data={{
                          labels: profitData.employee_performance.map(e => e.name),
                          datasets: [{
                            label: 'Orders Count',
                            data: profitData.employee_performance.map(e => e.orders_count),
                            backgroundColor: '#667eea',
                          }]
                        }}
                        options={{ responsive: true }}
                      />
                    </Card>
                  </Col>
                )}
              </Row>
            )
          }
        ]} />
      </div>

      {/* Approval Modal */}
      <Modal
        title={`✅ Approve Order #${approveModal.order?.order_number}`}
        open={approveModal.open}
        onCancel={() => setApproveModal({ open: false, order: null })}
        footer={null}
        width={480}
      >
        {approveModal.order && (
          <>
            <Card size="small" style={{ marginBottom: 16, background: '#f9f9f9' }}>
              <Row gutter={8}>
                <Col span={12}><Text type="secondary">Product:</Text><br /><Text strong>{approveModal.order.product_name}</Text></Col>
                <Col span={12}><Text type="secondary">Factory Cost:</Text><br /><Text strong style={{ color: '#cf1322' }}>${parseFloat(approveModal.order.factory_cost || 0).toFixed(2)}</Text></Col>
              </Row>
            </Card>
            <Form form={form} layout="vertical" onFinish={handleApprove}>
              <Form.Item name="profit_margin_percentage" label="Profit Margin (%)" rules={[{ required: true }]}
                help="Adjust margin before final approval">
                <InputNumber
                  min={0} max={500} step={1} style={{ width: '100%' }}
                  formatter={v => `${v}%`} parser={v => v.replace('%', '')}
                  onChange={v => {
                    const cost = parseFloat(approveModal.order.factory_cost || 0);
                    form.setFieldValue('suggested_price', (cost * (1 + v / 100)).toFixed(2));
                  }}
                />
              </Form.Item>
              <Form.Item name="suggested_price" label="Resulting Sale Price (USD)">
                <InputNumber disabled style={{ width: '100%' }} formatter={v => `$ ${v}`} />
              </Form.Item>
              <Row gutter={8}>
                <Col span={12}>
                  <Button block onClick={() => setApproveModal({ open: false, order: null })}>Cancel</Button>
                </Col>
                <Col span={12}>
                  <Button type="primary" htmlType="submit" block loading={approving} icon={<CheckCircleOutlined />}>
                    Approve Order
                  </Button>
                </Col>
              </Row>
            </Form>
          </>
        )}
      </Modal>
    </AppLayout>
  );
}
