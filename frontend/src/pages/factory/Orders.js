import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { Table, Card, Button, Tag, Space, message, Modal, Input, Select, DatePicker, Spin } from 'antd';
import { 
  EyeOutlined, 
  EditOutlined, 
  SearchOutlined,
  ToolOutlined
} from '@ant-design/icons';
import { useAuth } from '../../contexts/AuthContext';
import axios from 'axios';
import moment from 'moment';

const { Option } = Select;
const { RangePicker } = DatePicker;

const FactoryOrders = () => {
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(false);
  const [pagination, setPagination] = useState({ current: 1, pageSize: 20, total: 0 });
  const [filters, setFilters] = useState({});
  const [selectedOrder, setSelectedOrder] = useState(null);
  const [modalVisible, setModalVisible] = useState(false);
  const navigate = useNavigate();
  const { user } = useAuth();

  useEffect(() => {
    fetchOrders();
  }, [pagination.current, pagination.pageSize, filters]);

  const fetchOrders = async () => {
    try {
      setLoading(true);
      const params = {
        page: pagination.current,
        per_page: pagination.pageSize,
        ...filters,
      };

      const response = await axios.get('/api/orders', { params });
      setOrders(response.data.data);
      setPagination(prev => ({
        ...prev,
        total: response.data.total,
      }));
    } catch (error) {
      message.error('Failed to fetch orders');
    } finally {
      setLoading(false);
    }
  };

  const handleTableChange = (paginationConfig) => {
    setPagination(prev => ({
      ...prev,
      current: paginationConfig.current,
      pageSize: paginationConfig.pageSize,
    }));
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

  const columns = [
    {
      title: 'Order Number',
      dataIndex: 'order_number',
      key: 'order_number',
      render: (text) => <strong>{text}</strong>,
    },
    {
      title: 'Product',
      dataIndex: 'product_name',
      key: 'product_name',
    },
    {
      title: 'Quantity',
      dataIndex: 'quantity',
      key: 'quantity',
    },
    {
      title: 'Specifications',
      dataIndex: 'specifications',
      key: 'specifications',
      ellipsis: true,
    },
    {
      title: 'Factory Cost',
      dataIndex: 'factory_cost',
      key: 'factory_cost',
      render: (cost) => cost ? `$${Number(cost).toFixed(2)}` : '-',
    },
    {
      title: 'Production Days',
      dataIndex: 'production_days',
      key: 'production_days',
      render: (days) => days ? `${days} days` : '-',
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
      title: 'Created Date',
      dataIndex: 'created_at',
      key: 'created_at',
      render: (date) => moment(date).format('YYYY-MM-DD'),
    },
    {
      title: 'Actions',
      key: 'actions',
      render: (_, record) => (
        <Space size="small">
          <Button 
            size="small" 
            icon={<EyeOutlined />} 
            onClick={() => viewOrder(record)}
          >
            View
          </Button>
          {record.status === 'factory_pricing' && (
            <Button 
              size="small" 
              type="primary" 
              icon={<EditOutlined />} 
              onClick={() => navigate(`/factory/orders/${record.id}/edit`)}
            >
              Add Cost Details
            </Button>
          )}
          {record.status === 'manager_review' && (
            <Tag color="orange">Pending Manager Review</Tag>
          )}
        </Space>
      ),
    },
  ];

  return (
    <div style={{ padding: '24px' }}>
      <Card title="Factory Orders - Pending Pricing">
        {/* Filters */}
        <div style={{ marginBottom: '16px', display: 'flex', gap: '16px', flexWrap: 'wrap' }}>
          <Select
            placeholder="Filter by Status"
            style={{ width: 200 }}
            allowClear
            onChange={(value) => setFilters({ ...filters, status: value })}
          >
            <Option value="factory_pricing">Factory Pricing</Option>
            <Option value="manager_review">Manager Review</Option>
            <Option value="approved">Approved</Option>
            <Option value="customer_approved">Customer Approved</Option>
            <Option value="payment_confirmed">Payment Confirmed</Option>
            <Option value="completed">Completed</Option>
          </Select>
          
          <RangePicker
            placeholder={['Start Date', 'End Date']}
            onChange={(dates) => {
              if (dates) {
                setFilters({
                  ...filters,
                  date_from: dates[0].format('YYYY-MM-DD'),
                  date_to: dates[1].format('YYYY-MM-DD'),
                });
              } else {
                const { date_from, date_to, ...rest } = filters;
                setFilters(rest);
              }
            }}
          />
        </div>

        <Table
          columns={columns}
          dataSource={orders}
          rowKey="id"
          pagination={pagination}
          onChange={handleTableChange}
          loading={loading}
        />
      </Card>

      {/* Order Details Modal */}
      <Modal
        title="Order Details - Factory View"
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
                <p><strong>Product:</strong> {selectedOrder.product_name}</p>
                <p><strong>Quantity:</strong> {selectedOrder.quantity}</p>
                <p><strong>Status:</strong> <Tag color={getStatusColor(selectedOrder.status)}>{getStatusText(selectedOrder.status)}</Tag></p>
              </Col>
              <Col span={12}>
                <p><strong>Supplier:</strong> {selectedOrder.supplier_name || 'Not specified'}</p>
                <p><strong>Product Code:</strong> {selectedOrder.product_code || 'Not specified'}</p>
                <p><strong>Factory Cost:</strong> {selectedOrder.factory_cost ? `$${Number(selectedOrder.factory_cost).toFixed(2)}` : 'Not specified'}</p>
                <p><strong>Production Days:</strong> {selectedOrder.production_days ? `${selectedOrder.production_days} days` : 'Not specified'}</p>
              </Col>
            </Row>
            
            {selectedOrder.specifications && (
              <div style={{ marginTop: '16px' }}>
                <strong>Technical Specifications:</strong>
                <p>{selectedOrder.specifications}</p>
              </div>
            )}

            {selectedOrder.attachments && selectedOrder.attachments.length > 0 && (
              <div style={{ marginTop: '16px' }}>
                <strong>Attachments:</strong>
                <ul>
                  {selectedOrder.attachments.map((attachment) => (
                    <li key={attachment.id}>
                      <a href={attachment.full_url} target="_blank" rel="noopener noreferrer">
                        {attachment.original_name}
                      </a>
                      <span style={{ marginLeft: '8px', fontSize: '12px', color: '#666' }}>
                        ({attachment.type === 'sales_upload' ? 'Sales Upload' : 'Factory Upload'})
                      </span>
                    </li>
                  ))}
                </ul>
              </div>
            )}

            <div style={{ marginTop: '16px', padding: '12px', backgroundColor: '#fffbe6', border: '1px solid #ffe58f', borderRadius: '4px' }}>
              <strong style={{ color: '#d46b08 }}>🔒 Privacy Notice:</strong>
              <p style={{ margin: '8px 0 0 0', fontSize: '12px', color: '#d46b08' }}>
                Customer information is hidden for privacy. You can only view technical specifications and attachments.
              </p>
            </div>
          </div>
        )}
      </Modal>
    </div>
  );
};

export default FactoryOrders;
