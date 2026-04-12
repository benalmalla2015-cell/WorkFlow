import React, { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { Form, Input, Button, Card, Row, Col, Upload, message, Spin, InputNumber } from 'antd';
import { UploadOutlined, SaveOutlined, ArrowLeftOutlined } from '@ant-design/icons';
import { useAuth } from '../../contexts/AuthContext';
import AppLayout from '../../components/AppLayout';
import axios from 'axios';

const { TextArea } = Input;

const FactoryOrderForm = () => {
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);
  const [order, setOrder] = useState(null);
  const [fileList, setFileList] = useState([]);
  const navigate = useNavigate();
  const { id } = useParams();
  const { user } = useAuth();

  useEffect(() => {
    if (id) {
      fetchOrder();
    }
  }, [id]);

  const fetchOrder = async () => {
    try {
      setLoading(true);
      const response = await axios.get(`/api/orders/${id}`);
      
      if (response.data.status !== 'factory_pricing') {
        message.error('This order cannot be edited in its current status');
        navigate('/factory/orders');
        return;
      }
      
      setOrder(response.data);
      form.setFieldsValue({
        supplier_name: response.data.supplier_name,
        product_code: response.data.product_code,
        factory_cost: response.data.factory_cost,
        production_days: response.data.production_days,
      });
    } catch (error) {
      message.error('Failed to fetch order');
      navigate('/factory/orders');
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async (values) => {
    try {
      setLoading(true);
      const formData = new FormData();
      
      // Append order data
      Object.keys(values).forEach(key => {
        formData.append(key, values[key]);
      });

      // Append files
      fileList.forEach(file => {
        formData.append('attachments[]', file.originFileObj);
      });

      await axios.post(`/api/orders/${id}?_method=PUT`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      
      message.success('Cost details submitted successfully. Order is now pending manager review.');
      navigate('/factory/orders');
    } catch (error) {
      message.error(error.response?.data?.message || 'Failed to save cost details');
    } finally {
      setLoading(false);
    }
  };

  const uploadProps = {
    onRemove: (file) => {
      const index = fileList.indexOf(file);
      const newFileList = fileList.slice();
      newFileList.splice(index, 1);
      setFileList(newFileList);
    },
    beforeUpload: (file) => {
      const isValidType = ['image/jpeg', 'image/png', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'].includes(file.type);
      if (!isValidType) {
        message.error('You can only upload JPG/PNG images, PDF, Word, or Excel files!');
        return false;
      }
      const isLt10M = file.size / 1024 / 1024 < 10;
      if (!isLt10M) {
        message.error('File must be smaller than 10MB!');
        return false;
      }
      setFileList([...fileList, file]);
      return false; // Prevent automatic upload
    },
    fileList,
  };

  if (loading && id) {
    return <AppLayout><Spin size="large" style={{ display: 'block', margin: '50px auto' }} /></AppLayout>;
  }

  if (!order && id) {
    return null;
  }

  return (
    <AppLayout>
    <div style={{ padding: '0' }}>
      <Card title="Add Factory Cost Details">
        {/* Order Information Display */}
        {order && (
          <Card size="small" title="Order Information" style={{ marginBottom: '16px', backgroundColor: '#f5f5f5' }}>
            <Row gutter={16}>
              <Col span={8}>
                <p><strong>Order Number:</strong> {order.order_number}</p>
                <p><strong>Product:</strong> {order.product_name}</p>
              </Col>
              <Col span={8}>
                <p><strong>Quantity:</strong> {order.quantity}</p>
                <p><strong>Status:</strong> {order.status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</p>
              </Col>
              <Col span={8}>
                {order.specifications && (
                  <div>
                    <strong>Specifications:</strong>
                    <p style={{ fontSize: '12px' }}>{order.specifications}</p>
                  </div>
                )}
              </Col>
            </Row>
          </Card>
        )}

        <Form
          form={form}
          layout="vertical"
          onFinish={handleSubmit}
        >
          {/* Cost Information */}
          <Card size="small" title="Supplier & Cost Information" style={{ marginBottom: '16px' }}>
            <Row gutter={16}>
              <Col span={12}>
                <Form.Item
                  name="supplier_name"
                  label="Supplier Name"
                  rules={[{ required: true, message: 'Please enter supplier name' }]}
                >
                  <Input placeholder="Enter supplier name" />
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item
                  name="product_code"
                  label="Product Code"
                  rules={[{ required: true, message: 'Please enter product code' }]}
                >
                  <Input placeholder="Enter product code" />
                </Form.Item>
              </Col>
            </Row>
            <Row gutter={16}>
              <Col span={12}>
                <Form.Item
                  name="factory_cost"
                  label="Factory Cost (USD)"
                  rules={[{ required: true, message: 'Please enter factory cost' }]}
                >
                  <InputNumber
                    min={0}
                    step={0.01}
                    precision={2}
                    style={{ width: '100%' }}
                    placeholder="Enter factory cost"
                    formatter={value => `$ ${value}`.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}
                    parser={value => value.replace(/\$\s?|(,*)/g, '')}
                  />
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item
                  name="production_days"
                  label="Production Duration (Days)"
                  rules={[{ required: true, message: 'Please enter production duration' }]}
                >
                  <InputNumber
                    min={1}
                    style={{ width: '100%' }}
                    placeholder="Enter production days"
                  />
                </Form.Item>
              </Col>
            </Row>
          </Card>

          {/* Attachments */}
          <Card size="small" title="Purchase Attachments" style={{ marginBottom: '16px' }}>
            <Form.Item label="Upload Purchase Files (PDF, Excel, Word, Images)">
              <Upload {...uploadProps} multiple>
                <Button icon={<UploadOutlined />}>Select Files</Button>
              </Upload>
              <div style={{ marginTop: '8px', fontSize: '12px', color: '#666' }}>
                Upload purchase orders, invoices, or related documents (Max 10MB per file)
              </div>
            </Form.Item>
          </Card>

          {/* Privacy Notice */}
          <div style={{ marginBottom: '16px', padding: '12px', backgroundColor: '#fffbe6', border: '1px solid #ffe58f', borderRadius: '4px' }}>
            <strong style={{ color: '#d46b08' }}>🔒 Privacy Notice:</strong>
            <p style={{ margin: '8px 0 0 0', fontSize: '12px', color: '#d46b08' }}>
              Customer information is hidden for privacy. Only technical specifications and cost details will be visible to sales staff after manager approval.
            </p>
          </div>

          {/* Form Actions */}
          <Row justify="space-between">
            <Col>
              <Button 
                icon={<ArrowLeftOutlined />} 
                onClick={() => navigate('/factory/orders')}
              >
                Back to Orders
              </Button>
            </Col>
            <Col>
              <Button 
                type="primary" 
                htmlType="submit" 
                loading={loading}
                icon={<SaveOutlined />}
              >
                Submit Cost Details
              </Button>
            </Col>
          </Row>
        </Form>
      </Card>
    </div>
    </AppLayout>
  );
};

export default FactoryOrderForm;
