import React, { useState, useEffect } from 'react';
import { Form, Input, InputNumber, Button, Upload, Card, Row, Col, message, Space, Typography, Tag, Spin, Divider, Alert } from 'antd';
import { UploadOutlined, SaveOutlined, ArrowLeftOutlined, LockOutlined } from '@ant-design/icons';
import { useNavigate, useParams } from 'react-router-dom';
import AppLayout from '../../components/AppLayout';
import axios from 'axios';

const { Title, Text } = Typography;
const { TextArea } = Input;

const statusColors = {
  draft: 'default', factory_pricing: 'processing', manager_review: 'warning',
  approved: 'success', customer_approved: 'cyan', payment_confirmed: 'green', completed: 'purple'
};
const statusLabels = {
  draft: 'Draft', factory_pricing: 'Factory Pricing', manager_review: 'Manager Review',
  approved: 'Approved', customer_approved: 'Customer Approved', payment_confirmed: 'Payment Confirmed', completed: 'Completed'
};

export default function FactoryOrderForm() {
  const [form] = Form.useForm();
  const { id } = useParams();
  const navigate = useNavigate();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [order, setOrder] = useState(null);
  const [fileList, setFileList] = useState([]);

  useEffect(() => {
    if (id) {
      setLoading(true);
      axios.get(`/api/orders/${id}`)
        .then(res => {
          const o = res.data;
          setOrder(o);
          form.setFieldsValue({
            supplier_name: o.supplier_name,
            product_code: o.product_code,
            factory_cost: o.factory_cost,
            production_days: o.production_days,
          });
        })
        .catch(() => message.error('Failed to load order'))
        .finally(() => setLoading(false));
    }
  }, [id]);

  const onFinish = async (values) => {
    setSaving(true);
    try {
      const formData = new FormData();
      formData.append('supplier_name', values.supplier_name);
      formData.append('product_code', values.product_code);
      formData.append('factory_cost', values.factory_cost);
      formData.append('production_days', values.production_days);
      fileList.forEach(f => {
        if (f.originFileObj) formData.append('attachments[]', f.originFileObj);
      });

      await axios.post(`/api/orders/${id}?_method=PUT`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      message.success('Pricing submitted for manager review!');
      navigate('/factory/orders');
    } catch (err) {
      message.error(err.response?.data?.message || 'Failed to save pricing');
    } finally {
      setSaving(false);
    }
  };

  if (loading) return <AppLayout><Spin size="large" style={{ display: 'block', margin: '80px auto' }} /></AppLayout>;
  if (!order) return <AppLayout><Alert type="error" message="Order not found" /></AppLayout>;

  const canEdit = order.status === 'factory_pricing';

  return (
    <AppLayout>
      <div style={{ padding: '24px', maxWidth: 900, margin: '0 auto' }}>
        <Row align="middle" justify="space-between" style={{ marginBottom: 20 }}>
          <Col>
            <Space>
              <Button icon={<ArrowLeftOutlined />} onClick={() => navigate('/factory/orders')}>Back</Button>
              <Title level={4} style={{ margin: 0 }}>Order #{order.order_number}</Title>
              <Tag color={statusColors[order.status]}>{statusLabels[order.status]}</Tag>
            </Space>
          </Col>
        </Row>

        {/* Order Product Info - visible to factory */}
        <Card title="📦 Product Specifications" style={{ marginBottom: 16 }}>
          <Row gutter={16}>
            <Col span={12}>
              <Text strong>Product Name: </Text><Text>{order.product_name}</Text>
            </Col>
            <Col span={12}>
              <Text strong>Quantity: </Text><Text>{order.quantity} PCS</Text>
            </Col>
            {order.specifications && (
              <Col span={24} style={{ marginTop: 8 }}>
                <Text strong>Specifications: </Text>
                <Text>{order.specifications}</Text>
              </Col>
            )}
          </Row>
        </Card>

        {/* Privacy Notice */}
        <Alert
          icon={<LockOutlined />}
          type="warning"
          showIcon
          style={{ marginBottom: 16 }}
          message="Privacy Notice"
          description="Customer identity, contact information, and sales notes are hidden for privacy. You can only see product specifications and your own pricing data."
        />

        {/* Factory Attachments from sales */}
        {order.attachments?.filter(a => a.type === 'sales_upload').length > 0 && (
          <Card title="📎 Sales Reference Files" style={{ marginBottom: 16 }}>
            {order.attachments.filter(a => a.type === 'sales_upload').map(att => (
              <div key={att.id} style={{ marginBottom: 4 }}>
                <a href={`/api/attachments/${att.id}/download`} target="_blank" rel="noreferrer">
                  📄 {att.original_name}
                </a>
              </div>
            ))}
          </Card>
        )}

        {/* Pricing Form */}
        <Form form={form} layout="vertical" onFinish={onFinish} disabled={!canEdit}>
          <Card title="💰 Factory Pricing & Supplier Info" style={{ marginBottom: 16 }}>
            <Row gutter={16}>
              <Col span={12}>
                <Form.Item name="supplier_name" label="Supplier Name" rules={[{ required: true }]}>
                  <Input placeholder="Supplier company name" />
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item name="product_code" label="Product Code / SKU" rules={[{ required: true }]}>
                  <Input placeholder="e.g. PRD-2025-001" />
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item name="factory_cost" label="Factory Cost (USD)" rules={[{ required: true }]}>
                  <InputNumber
                    min={0} step={0.01} style={{ width: '100%' }}
                    placeholder="Cost per unit in USD"
                    formatter={v => `$ ${v}`} parser={v => v.replace(/\$\s?|(,*)/g, '')}
                  />
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item name="production_days" label="Production Lead Time (Days)" rules={[{ required: true }]}>
                  <InputNumber min={1} style={{ width: '100%' }} placeholder="e.g. 30" />
                </Form.Item>
              </Col>
            </Row>
          </Card>

          <Card title="📎 Factory Attachments (Quotes, Proforma, Images)" style={{ marginBottom: 16 }}>
            <Upload
              beforeUpload={() => false}
              fileList={fileList}
              onChange={({ fileList: fl }) => setFileList(fl)}
              multiple
              accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
            >
              <Button icon={<UploadOutlined />}>Add Factory Files</Button>
            </Upload>
            {order.attachments?.filter(a => a.type === 'factory_upload').length > 0 && (
              <div style={{ marginTop: 12 }}>
                <Text strong>Existing Factory Files:</Text>
                {order.attachments.filter(a => a.type === 'factory_upload').map(att => (
                  <div key={att.id} style={{ marginTop: 4 }}>
                    <a href={`/api/attachments/${att.id}/download`} target="_blank" rel="noreferrer">
                      📄 {att.original_name}
                    </a>
                  </div>
                ))}
              </div>
            )}
          </Card>

          {canEdit && (
            <Button type="primary" htmlType="submit" icon={<SaveOutlined />} loading={saving} size="large" block
              style={{ background: '#52c41a', borderColor: '#52c41a' }}>
              Submit Pricing for Manager Review
            </Button>
          )}

          {!canEdit && (
            <Alert type="info" message={`Order is currently in "${statusLabels[order.status]}" status. No further edits needed.`} />
          )}
        </Form>
      </div>
    </AppLayout>
  );
}
