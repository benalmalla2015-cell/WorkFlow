import React, { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { Form, Input, Button, Card, Row, Col, Upload, message, Spin, Select, InputNumber } from 'antd';
import { UploadOutlined, SaveOutlined, ArrowLeftOutlined } from '@ant-design/icons';
import { useAuth } from '../../contexts/AuthContext';
import AppLayout from '../../components/AppLayout';
import axios from 'axios';

const { TextArea } = Input;
const { Option } = Select;

const SalesOrderForm = () => {
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
      setOrder(response.data);
      form.setFieldsValue({
        customer: {
          full_name: response.data.customer?.full_name,
          address: response.data.customer?.address,
          phone: response.data.customer?.phone,
          email: response.data.customer?.email,
          notes: response.data.customer?.notes,
        },
        product_name: response.data.product_name,
        quantity: response.data.quantity,
        specifications: response.data.specifications,
        customer_notes: response.data.customer_notes,
      });
    } catch (error) {
      message.error('Failed to fetch order');
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
        if (key === 'customer') {
          Object.keys(values.customer).forEach(customerKey => {
            formData.append(`customer[${customerKey}]`, values.customer[customerKey]);
          });
        } else {
          formData.append(key, values[key]);
        }
      });

      // Append files
      fileList.forEach(file => {
        formData.append('attachments[]', file.originFileObj);
      });

      if (id) {
        await axios.post(`/api/orders/${id}?_method=PUT`, formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        });
        message.success('Order updated successfully');
      } else {
        await axios.post('/api/orders', formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        });
        message.success('Order created successfully');
      }
      
      navigate('/sales/orders');
    } catch (error) {
      message.error(error.response?.data?.message || 'Failed to save order');
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

  return (
    <AppLayout>
    <div style={{ padding: '0' }}>
      <Card title={id ? 'Edit Order' : 'Create New Order'}>
        <Form
          form={form}
          layout="vertical"
          onFinish={handleSubmit}
          initialValues={{
            quantity: 1,
          }}
        >
          {/* Customer Information */}
          <Card size="small" title="Customer Information" style={{ marginBottom: '16px' }}>
            <Row gutter={16}>
              <Col span={12}>
                <Form.Item
                  name={['customer', 'full_name']}
                  label="Customer Full Name"
                  rules={[{ required: true, message: 'Please enter customer full name' }]}
                >
                  <Input placeholder="Enter customer full name" />
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item
                  name={['customer', 'phone']}
                  label="Phone Number"
                  rules={[{ required: true, message: 'Please enter phone number' }]}
                >
                  <Input placeholder="Enter phone number" />
                </Form.Item>
              </Col>
            </Row>
            <Row gutter={16}>
              <Col span={24}>
                <Form.Item
                  name={['customer', 'address']}
                  label="Address"
                  rules={[{ required: true, message: 'Please enter address' }]}
                >
                  <TextArea rows={2} placeholder="Enter customer address" />
                </Form.Item>
              </Col>
            </Row>
            <Row gutter={16}>
              <Col span={12}>
                <Form.Item
                  name={['customer', 'email']}
                  label="Email (Optional)"
                  rules={[{ type: 'email', message: 'Please enter a valid email' }]}
                >
                  <Input placeholder="Enter email address" />
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item
                  name={['customer', 'notes']}
                  label="Customer Notes (Optional)"
                >
                  <TextArea rows={2} placeholder="Enter any additional notes" />
                </Form.Item>
              </Col>
            </Row>
          </Card>

          {/* Product Information */}
          <Card size="small" title="Product Information" style={{ marginBottom: '16px' }}>
            <Row gutter={16}>
              <Col span={24}>
                <Form.Item
                  name="product_name"
                  label="Product Name"
                  rules={[{ required: true, message: 'Please enter product name' }]}
                >
                  <Input placeholder="Enter product name" />
                </Form.Item>
              </Col>
            </Row>
            <Row gutter={16}>
              <Col span={8}>
                <Form.Item
                  name="quantity"
                  label="Quantity"
                  rules={[{ required: true, message: 'Please enter quantity' }]}
                >
                  <InputNumber
                    min={1}
                    style={{ width: '100%' }}
                    placeholder="Enter quantity"
                  />
                </Form.Item>
              </Col>
              <Col span={16}>
                <Form.Item
                  name="specifications"
                  label="Specifications (Optional)"
                >
                  <TextArea rows={3} placeholder="Enter product specifications" />
                </Form.Item>
              </Col>
            </Row>
            <Row gutter={16}>
              <Col span={24}>
                <Form.Item
                  name="customer_notes"
                  label="Order Notes (Private - Visible to Sales and Admin Only)"
                >
                  <TextArea rows={3} placeholder="Enter internal notes about this order" />
                </Form.Item>
              </Col>
            </Row>
          </Card>

          {/* Attachments */}
          <Card size="small" title="Attachments" style={{ marginBottom: '16px' }}>
            <Form.Item label="Upload Files (Word, Excel, PDF, Images)">
              <Upload {...uploadProps} multiple>
                <Button icon={<UploadOutlined />}>Select Files</Button>
              </Upload>
              <div style={{ marginTop: '8px', fontSize: '12px', color: '#666' }}>
                Supported formats: JPG, PNG, PDF, Word, Excel (Max 10MB per file)
              </div>
            </Form.Item>
          </Card>

          {/* Form Actions */}
          <Row justify="space-between">
            <Col>
              <Button 
                icon={<ArrowLeftOutlined />} 
                onClick={() => navigate('/sales/orders')}
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
                {id ? 'Update Order' : 'Create Order'}
              </Button>
            </Col>
          </Row>
        </Form>
      </Card>
    </div>
    </AppLayout>
  );
};

export default SalesOrderForm;
