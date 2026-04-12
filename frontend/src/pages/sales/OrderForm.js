import React, { useState, useEffect } from 'react';
import { Form, Input, InputNumber, Button, Upload, Card, Row, Col, message, Space, Typography, Divider, Spin, Tag } from 'antd';
import { UploadOutlined, SaveOutlined, ArrowLeftOutlined, FilePdfOutlined, FileExcelOutlined, CheckCircleOutlined, DollarOutlined, SendOutlined } from '@ant-design/icons';
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

export default function SalesOrderForm() {
  const [form] = Form.useForm();
  const { id } = useParams();
  const navigate = useNavigate();
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [order, setOrder] = useState(null);
  const [fileList, setFileList] = useState([]);
  const [docLoading, setDocLoading] = useState({});

  const isEdit = Boolean(id);

  useEffect(() => {
    if (isEdit) {
      setLoading(true);
      axios.get(`/api/orders/${id}`)
        .then(res => {
          const o = res.data;
          setOrder(o);
          form.setFieldsValue({
            customer_full_name: o.customer?.full_name,
            customer_address: o.customer?.address,
            customer_phone: o.customer?.phone,
            customer_email: o.customer?.email,
            product_name: o.product_name,
            quantity: o.quantity,
            specifications: o.specifications,
            customer_notes: o.customer_notes,
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
      formData.append('customer[full_name]', values.customer_full_name);
      formData.append('customer[address]', values.customer_address);
      formData.append('customer[phone]', values.customer_phone);
      if (values.customer_email) formData.append('customer[email]', values.customer_email);
      formData.append('product_name', values.product_name);
      formData.append('quantity', values.quantity);
      if (values.specifications) formData.append('specifications', values.specifications);
      if (values.customer_notes) formData.append('customer_notes', values.customer_notes);

      fileList.forEach(f => {
        if (f.originFileObj) formData.append('attachments[]', f.originFileObj);
      });

      if (isEdit) {
        await axios.post(`/api/orders/${id}?_method=PUT`, formData, { headers: { 'Content-Type': 'multipart/form-data' } });
        message.success('Order updated successfully');
      } else {
        const res = await axios.post('/api/orders', formData, { headers: { 'Content-Type': 'multipart/form-data' } });
        message.success('Order created successfully');
        navigate(`/sales/orders/${res.data.order.id}/edit`);
      }
    } catch (err) {
      message.error(err.response?.data?.message || 'Failed to save order');
    } finally {
      setSaving(false);
    }
  };

  const handleAction = async (action, label) => {
    setDocLoading(p => ({ ...p, [action]: true }));
    try {
      let res;
      if (action === 'send_to_factory') {
        res = await axios.patch(`/api/orders/${id}/status`, { status: 'factory_pricing' });
        message.success('Order submitted to factory pricing');
        setOrder(p => ({ ...p, status: 'factory_pricing' }));
      } else if (action === 'customer_approval') {
        res = await axios.post(`/api/orders/${id}/customer-approval`);
        message.success('Customer approval recorded');
        setOrder(p => ({ ...p, status: 'customer_approved', customer_approval: true }));
      } else if (action === 'confirm_payment') {
        res = await axios.post(`/api/orders/${id}/confirm-payment`);
        message.success('Payment confirmed');
        setOrder(p => ({ ...p, status: 'completed', payment_confirmed: true }));
      } else if (action === 'generate_quotation') {
        res = await axios.post(`/api/orders/${id}/quotation`);
        message.success('Quotation generated!');
        window.open(`/api/orders/${id}/download-quotation`, '_blank');
      } else if (action === 'generate_invoice') {
        res = await axios.post(`/api/orders/${id}/invoice`);
        message.success('Invoice generated!');
        window.open(`/api/orders/${id}/download-invoice`, '_blank');
      } else if (action === 'download_quotation') {
        window.open(`/api/orders/${id}/download-quotation`, '_blank');
      } else if (action === 'download_invoice') {
        window.open(`/api/orders/${id}/download-invoice`, '_blank');
      }
    } catch (err) {
      message.error(err.response?.data?.message || `Failed: ${label}`);
    } finally {
      setDocLoading(p => ({ ...p, [action]: false }));
    }
  };

  const uploadProps = {
    beforeUpload: () => false,
    fileList,
    onChange: ({ fileList: fl }) => setFileList(fl),
    multiple: true,
    accept: '.pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png',
  };

  if (loading) return <AppLayout><Spin size="large" style={{ display: 'block', margin: '80px auto' }} /></AppLayout>;

  const canEdit = !order || order?.status === 'draft';
  const isApproved = ['approved', 'customer_approved', 'payment_confirmed', 'completed'].includes(order?.status);
  const isCustomerApproved = ['customer_approved', 'payment_confirmed', 'completed'].includes(order?.status);

  return (
    <AppLayout>
      <div style={{ padding: '24px', maxWidth: 900, margin: '0 auto' }}>
        <Row align="middle" justify="space-between" style={{ marginBottom: 20 }}>
          <Col>
            <Space>
              <Button icon={<ArrowLeftOutlined />} onClick={() => navigate('/sales/orders')}>Back</Button>
              <Title level={4} style={{ margin: 0 }}>
                {isEdit ? `Edit Order #${order?.order_number}` : 'New Sales Order'}
              </Title>
              {order && <Tag color={statusColors[order.status]}>{statusLabels[order.status]}</Tag>}
            </Space>
          </Col>
        </Row>

        <Form form={form} layout="vertical" onFinish={onFinish} disabled={!canEdit && isEdit}>
          <Card title="👤 Customer Information" style={{ marginBottom: 16 }}>
            <Row gutter={16}>
              <Col span={12}>
                <Form.Item name="customer_full_name" label="Full Name" rules={[{ required: true }]}>
                  <Input placeholder="Customer full name" />
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item name="customer_phone" label="Phone / Contact" rules={[{ required: true }]}>
                  <Input placeholder="+966 5X XXX XXXX" />
                </Form.Item>
              </Col>
              <Col span={16}>
                <Form.Item name="customer_address" label="Address" rules={[{ required: true }]}>
                  <Input placeholder="Full address" />
                </Form.Item>
              </Col>
              <Col span={8}>
                <Form.Item name="customer_email" label="Email (optional)">
                  <Input placeholder="email@example.com" />
                </Form.Item>
              </Col>
            </Row>
          </Card>

          <Card title="📦 Product Details" style={{ marginBottom: 16 }}>
            <Row gutter={16}>
              <Col span={16}>
                <Form.Item name="product_name" label="Product Name / Item Name" rules={[{ required: true }]}>
                  <Input placeholder="Product or item name" />
                </Form.Item>
              </Col>
              <Col span={8}>
                <Form.Item name="quantity" label="Quantity (PCS)" rules={[{ required: true }]}>
                  <InputNumber min={1} style={{ width: '100%' }} placeholder="e.g. 100" />
                </Form.Item>
              </Col>
              <Col span={24}>
                <Form.Item name="specifications" label="Technical Specifications">
                  <TextArea rows={3} placeholder="Size, material, color, HS code, barcode, packaging details…" />
                </Form.Item>
              </Col>
              <Col span={24}>
                <Form.Item name="customer_notes" label="Order Notes (internal)">
                  <TextArea rows={2} placeholder="Any additional notes about this order…" />
                </Form.Item>
              </Col>
            </Row>
          </Card>

          <Card title="📎 Attachments (Word, Excel, PDF, Images)" style={{ marginBottom: 16 }}>
            <Upload {...uploadProps}>
              <Button icon={<UploadOutlined />}>Select Files (multiple allowed)</Button>
            </Upload>
            <Text type="secondary" style={{ fontSize: 12, display: 'block', marginTop: 8 }}>
              Supported: .pdf .doc .docx .xls .xlsx .jpg .png — Max 10MB per file
            </Text>
            {order?.attachments?.filter(a => a.type === 'sales_upload').length > 0 && (
              <div style={{ marginTop: 12 }}>
                <Text strong>Existing Attachments:</Text>
                {order.attachments.filter(a => a.type === 'sales_upload').map(att => (
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
            <Space direction="vertical" style={{ width: '100%' }}>
              <Button type="primary" htmlType="submit" icon={<SaveOutlined />} loading={saving} size="large" block>
                {isEdit ? 'Update Order' : 'Create Order'}
              </Button>
              {isEdit && order?.status === 'draft' && (
                <Button
                  icon={<SendOutlined />}
                  loading={docLoading.send_to_factory}
                  size="large"
                  block
                  onClick={() => handleAction('send_to_factory', 'Submit to Factory')}
                >
                  Submit to Factory Pricing
                </Button>
              )}
            </Space>
          )}
        </Form>

        {isEdit && isApproved && (
          <>
            <Divider>Actions & Documents</Divider>
            <Card title="📄 Documents" style={{ marginBottom: 16 }}>
              <Space wrap>
                <Button
                  type="primary" icon={<FileExcelOutlined />}
                  loading={docLoading.generate_quotation}
                  onClick={() => handleAction('generate_quotation', 'Generate Quotation')}
                >Generate Quotation (Excel)</Button>
                {order?.quotation_path && (
                  <Button icon={<FileExcelOutlined />} onClick={() => handleAction('download_quotation', 'Download')}>
                    ⬇ Download Quotation
                  </Button>
                )}
                {(order?.payment_confirmed || order?.status === 'completed') && (
                  <Button
                    type="primary" danger icon={<FilePdfOutlined />}
                    loading={docLoading.generate_invoice}
                    onClick={() => handleAction('generate_invoice', 'Generate Invoice')}
                  >Generate Invoice (PDF)</Button>
                )}
                {order?.invoice_path && (
                  <Button icon={<FilePdfOutlined />} onClick={() => handleAction('download_invoice', 'Download')}>
                    ⬇ Download Invoice
                  </Button>
                )}
              </Space>
            </Card>

            <Card title="✅ Order Actions">
              <Space wrap>
                {order?.status === 'approved' && !order?.customer_approval && (
                  <Button
                    type="primary" icon={<CheckCircleOutlined />}
                    loading={docLoading.customer_approval}
                    onClick={() => handleAction('customer_approval', 'Customer Approval')}
                    style={{ background: '#52c41a', borderColor: '#52c41a' }}
                  >Record Customer Approval</Button>
                )}
                {order?.status === 'customer_approved' && !order?.payment_confirmed && (
                  <Button
                    type="primary" icon={<DollarOutlined />}
                    loading={docLoading.confirm_payment}
                    onClick={() => handleAction('confirm_payment', 'Confirm Payment')}
                    style={{ background: '#faad14', borderColor: '#faad14' }}
                  >Confirm Payment Received</Button>
                )}
                {order?.status === 'completed' && (
                  <Tag color="green" style={{ fontSize: 14, padding: '4px 12px' }}>✅ Order Completed</Tag>
                )}
              </Space>
            </Card>
          </>
        )}

        {isEdit && order?.status === 'factory_pricing' && (
          <Card title="ℹ️ Order Status" style={{ marginTop: 16, background: '#fffbe6', border: '1px solid #ffe58f' }}>
            <Text>This order is currently being priced by the factory team. You will be notified once pricing is complete and the manager approves.</Text>
          </Card>
        )}

        {isEdit && order?.status === 'manager_review' && (
          <Card title="ℹ️ Pending Manager Approval" style={{ marginTop: 16, background: '#e6f7ff', border: '1px solid #91d5ff' }}>
            <Text>Factory pricing is complete. Waiting for manager review and approval.</Text>
          </Card>
        )}
      </div>
    </AppLayout>
  );
}
