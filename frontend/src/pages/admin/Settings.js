import React, { useState, useEffect } from 'react';
import { Card, Form, Input, Button, Row, Col, message, Spin, InputNumber } from 'antd';
import { SaveOutlined } from '@ant-design/icons';
import axios from 'axios';
import AppLayout from '../../components/AppLayout';

const Settings = () => {
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);
  const [settings, setSettings] = useState({});

  useEffect(() => {
    fetchSettings();
  }, []);

  const fetchSettings = async () => {
    try {
      setLoading(true);
      const response = await axios.get('/api/admin/settings');
      setSettings(response.data);
      form.setFieldsValue(response.data);
    } catch (error) {
      message.error('Failed to fetch settings');
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async (values) => {
    try {
      setLoading(true);
      await axios.put('/api/admin/settings', values);
      message.success('Settings updated successfully');
      fetchSettings();
    } catch (error) {
      message.error(error.response?.data?.message || 'Failed to update settings');
    } finally {
      setLoading(false);
    }
  };

  if (loading && Object.keys(settings).length === 0) {
    return <Spin size="large" style={{ display: 'block', margin: '50px auto' }} />;
  }

  return (
    <AppLayout>
    <div style={{ padding: '0' }}>
      <Card title="System Settings">
        <Form
          form={form}
          layout="vertical"
          onFinish={handleSubmit}
        >
          {/* Company Settings */}
          <Card size="small" title="Company Information" style={{ marginBottom: '16px' }}>
            <Row gutter={16}>
              <Col span={12}>
                <Form.Item
                  name="company_name"
                  label="Company Name"
                  rules={[{ required: true, message: 'Please enter company name' }]}
                >
                  <Input placeholder="Enter company name" />
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item
                  name="company_phone"
                  label="Company Phone"
                  rules={[{ required: true, message: 'Please enter company phone' }]}
                >
                  <Input placeholder="Enter company phone" />
                </Form.Item>
              </Col>
            </Row>
            <Row gutter={16}>
              <Col span={24}>
                <Form.Item
                  name="company_address"
                  label="Company Address"
                  rules={[{ required: true, message: 'Please enter company address' }]}
                >
                  <Input.TextArea rows={2} placeholder="Enter company address" />
                </Form.Item>
              </Col>
            </Row>
          </Card>

          {/* Financial Settings */}
          <Card size="small" title="Financial Settings" style={{ marginBottom: '16px' }}>
            <Row gutter={16}>
              <Col span={12}>
                <Form.Item
                  name="default_profit_margin"
                  label="Default Profit Margin (%)"
                  rules={[{ required: true, message: 'Please enter default profit margin' }]}
                >
                  <InputNumber
                    min={0}
                    max={100}
                    step={0.1}
                    precision={1}
                    style={{ width: '100%' }}
                    placeholder="Enter default profit margin"
                  />
                </Form.Item>
              </Col>
            </Row>
          </Card>

          {/* Bank Details */}
          <Card size="small" title="Bank Details for Invoices" style={{ marginBottom: '16px' }}>
            <Row gutter={16}>
              <Col span={12}>
                <Form.Item
                  name="beneficiary_name"
                  label="Beneficiary Name"
                >
                  <Input placeholder="Enter beneficiary name" />
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item
                  name="beneficiary_bank"
                  label="Beneficiary Bank"
                >
                  <Input placeholder="Enter beneficiary bank" />
                </Form.Item>
              </Col>
            </Row>
            <Row gutter={16}>
              <Col span={12}>
                <Form.Item
                  name="account_number"
                  label="Account Number"
                >
                  <Input placeholder="Enter account number" />
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item
                  name="swift_code"
                  label="SWIFT Code"
                >
                  <Input placeholder="Enter SWIFT code" />
                </Form.Item>
              </Col>
            </Row>
            <Row gutter={16}>
              <Col span={24}>
                <Form.Item
                  name="bank_address"
                  label="Bank Address"
                >
                  <Input.TextArea rows={2} placeholder="Enter bank address" />
                </Form.Item>
              </Col>
            </Row>
          </Card>

          {/* Form Actions */}
          <Row justify="end">
            <Col>
              <Button 
                type="primary" 
                htmlType="submit" 
                loading={loading}
                icon={<SaveOutlined />}
              >
                Save Settings
              </Button>
            </Col>
          </Row>
        </Form>
      </Card>
    </div>
    </AppLayout>
  );
};

export default Settings;
